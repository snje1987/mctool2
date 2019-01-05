<?php

namespace Org\Snje\MCTool\MC;

use Org\Snje\MCTool;
use Exception;

class Region {

    const WIDTH = 32;
    const BLOCK_SIZE = 4096;

    protected $file;
    protected $fh;
    protected $offsets;
    protected $times;
    protected $base_coord;
    protected $chunks;

    /**
     *
     * @param mixed $chunk_coord 格式可能为[x,y]，或者为区块位置的序号，会统一转化为[x,y]的形式
     */
    public static function get_chunk_index($chunk_coord) {

        $chunk_coord = World::format_coord($chunk_coord, self::WIDTH);

        $chunk_coord[0] = MCTool\App::mod($chunk_coord[0], self::WIDTH);
        $chunk_coord[1] = MCTool\App::mod($chunk_coord[1], self::WIDTH);

        $chunk_coord[0] = $chunk_coord[0] < 0 ? $chunk_coord[0] + self::WIDTH : $chunk_coord[0];
        $chunk_coord[1] = $chunk_coord[1] < 0 ? $chunk_coord[1] + self::WIDTH : $chunk_coord[1];

        return $chunk_coord[0] + $chunk_coord[1] * self::WIDTH;
    }

    public static function get_file_region($file) {
        $filename = basename($file);
        $file_info = explode('.', $filename);
        if (count($file_info) != 4 || $file_info[3] !== 'mca') {
            throw new Exception('不支持的文件');
        }
        $ret = [];
        $ret['x'] = [$file_info[1] * self::WIDTH, $file_info[1] * self::WIDTH + self::WIDTH - 1];
        $ret['z'] = [$file_info[2] * self::WIDTH, $file_info[2] * self::WIDTH + self::WIDTH - 1];
        return $ret;
    }

    public static function encode_nbt($nbt, $compress = 'zlib') {
        $fp = fopen('php://memory', 'wb+');
        $nbt->save($fp);
        $size = ftell($fp);
        rewind($fp);
        $data = fread($fp, $size);
        fclose($fp);

        if ($compress === 'zlib') {
            $zip_data = zlib_encode($data, ZLIB_ENCODING_DEFLATE);
        }
        elseif ($compress === 'gzip') {
            $zip_data = gzencode($data);
        }

        return $zip_data;
    }

    public static function decode_nbt($data, $compress = 'zlib') {
        if ($compress === 'zlib') {
            $data = zlib_decode($data);
        }
        elseif ($compress === 'gzip') {
            $data = gzdecode($data);
        }

        $fp = fopen('php://memory', 'wb+');
        if ($fp == false) {
            throw new Exception('解码失败');
        }
        fwrite($fp, $data);
        rewind($fp);

        $nbt = new NBT();
        $nbt->load($fp);

        fclose($fp);

        return $nbt;
    }

    public function get_chunk_coord($chunk_index) {
        $x = MCTool\App::mod($chunk_index, self::WIDTH);
        $y = floor($chunk_index / self::WIDTH);
        return [$x + $this->base_coord[0], $y + $this->base_coord[1]];
    }

    public function __construct($path, $is_new = false) {
        $this->offsets = [];
        $this->times = [];
        $this->chunks = [];

        $this->file = $path;

        $region = self::get_file_region($this->file);
        $this->base_coord = [$region['x'][0], $region['z'][0]];

        if ($is_new) {
            $this->fh = fopen($this->file, 'wb+');
            ftruncate($this->fh, self::BLOCK_SIZE * 2);
        }
        else {
            $this->fh = fopen($this->file, 'rb+');
            $this->load();
        }
    }

    public function __destruct() {
        fclose($this->fh);
    }

    public function load() {
        $count = self::WIDTH * self::WIDTH;

        $offset_data = fread($this->fh, self::BLOCK_SIZE);
        $time_data = fread($this->fh, self::BLOCK_SIZE);

        for ($i = 0; $i < $count; $i ++) {
            $b1 = ord($offset_data{$i * 4});
            $b2 = ord($offset_data{$i * 4 + 1});
            $b3 = ord($offset_data{$i * 4 + 2});
            $len = ord($offset_data{$i * 4 + 3});
            $offset = ($b1 << 16) | ($b2 << 8) | $b3;

            $time_stamp = unpack('N', $time_data, $i * 4)[1];

            $this->offsets[$i] = [$offset, $len];
            $this->times[$i] = $time_stamp;
        }
    }

    /**
     * @param int $index
     * @return MCTool\MC\Chunk
     * @throws Exception
     */
    public function get_chunk($index) {
        if ($index < 0 || $index >= self::WIDTH * self::WIDTH) {
            throw new Exception('超出范围');
        }

        if (!isset($this->chunks[$index])) {
            $this->chunks[$index] = $this->load_chunk($index);
        }

        return $this->chunks[$index];
    }

    public function load_chunk($index) {

        if (!isset($this->offsets[$index]) || $this->offsets[$index][0] == 0) {
            return null;
        }

        fseek($this->fh, $this->offsets[$index][0] * self::BLOCK_SIZE);
        $data_len = unpack('N', fread($this->fh, 4))[1];

        $compress_type = unpack('C', fread($this->fh, 1))[1];
        $zip_data = fread($this->fh, $data_len - 1);

        $chunk = new Chunk();
        $chunk->set_data($this->times[$index], $compress_type, $zip_data);

        return $chunk;
    }

    public function show_chunks($out_fh) {
        $count = self::WIDTH * self::WIDTH;
        for ($i = 0; $i < $count; $i ++) {
            if (!isset($this->offsets[$i]) || $this->offsets[$i][0] == 0) {
                continue;
            }
            $coord = $this->get_chunk_coord($i);

            $str = $i . ' (' . $coord[0] . ',' . $coord[1] . ') => ' . date('Y-m-d H:i:s', $this->times[$i]) . ', ' . $this->offsets[$i][1] * 4 . " KB\n";
            fwrite($out_fh, $str);
        }
    }

    /**
     * @param \Org\Snje\MCTool\RegionList $region_list
     * @param function $callback
     * @param mixed $args
     * @return mixed
     */
    public function walk($region_list, $callback, $args) {
        $ret = null;
        $chunks = $region_list->get_chunk_list();
        $count = self::WIDTH * self::WIDTH;

        for ($i = 0; $i < $count; $i ++) {
            if (!isset($chunks[$i]) || $chunks[$i] == 0) {
                continue;
            }
            $ret = call_user_func($callback, $i, $args, $ret);
        }

        return $ret;
    }

    /**
     * @param int $index
     * @param \Org\Snje\MCTool\MC\Region $to
     * @param mixed $ret
     */
    public function copy_chunk($index, $to, $ret) {
        $chunk = $this->get_chunk($index);
        $to->add_chunk($index, $chunk);
    }

    /**
     * @param int $index
     * @param array $task
     * @param mixed $ret
     */
    public function reset_trade($index, $task, $ret) {
        $do = isset($task['do']) ? $task['do'] : null;
        if (!is_array($do) || empty($do)) {
            return;
        }

        $chunk = $this->get_chunk($index);
        if ($chunk === null) {
            return;
        }

        $nbt = $chunk->decode_nbt();
        $entities = NBT::get_node($nbt->root, ['', 'Level', 'Entities'], NBT::TAG_COMPOUND, NBT::TAG_LIST);
        if ($entities === null || !isset($entities['value'])) {
            return;
        }

        if (!isset($entities['type']) || $entities['type'] !== NBT::TAG_COMPOUND) {
            return;
        }

        $list = $entities['value'];

        $changed = false;

        foreach ($list as $k => $v) {
            $id = NBT::get_node($v, ['id'], NBT::TAG_COMPOUND, NBT::TAG_STRING);
            if ($id === null || $id !== 'minecraft:villager') {
                continue;
            }

            if (isset($task['name'])) {
                $name = NBT::get_node($v, ['CustomName'], NBT::TAG_COMPOUND, NBT::TAG_STRING);
                if ($name !== null) {
                    $data = json_decode($name, true);
                    $name = isset($data['text']) ? strval($data['text']) : null;
                }
                if ($name === null || $name !== $task['name']) {
                    continue;
                }
            }

            $recipes = NBT::get_node($v, ['Offers', 'Recipes'], NBT::TAG_COMPOUND, NBT::TAG_LIST);
            if ($recipes === null || !isset($recipes['value'])) {
                continue;
            }

            if (!isset($recipes['type']) || $recipes['type'] !== NBT::TAG_COMPOUND) {
                return;
            }

            foreach ($recipes['value'] as $ind => $recipe) {
                if (isset($do['max_uses'])) {
                    NBT::set_node($recipe, ['maxUses'], NBT::TAG_COMPOUND, $do['max_uses']);
                    $changed = true;
                }
                if (isset($do['uses'])) {
                    NBT::set_node($recipe, ['uses'], NBT::TAG_COMPOUND, $do['uses']);
                    $changed = true;
                }
                $recipes['value'][$ind] = $recipe;
            }
            if (!$changed) {
                continue;
            }
            NBT::set_node($v, ['Offers', 'Recipes'], NBT::TAG_COMPOUND, $recipes);
            $list[$k] = $v;
        }
        if ($changed) {
            $entities['value'] = $list;
            NBT::set_node($nbt->root, ['', 'Level', 'Entities'], NBT::TAG_COMPOUND, $entities);
            $chunk->set_nbt($nbt);
        }
    }

    /**
     * @param \Org\Snje\MCTool\MC\Chunk $chunk
     */
    public function add_chunk($index, $chunk) {
        if ($index < 0 || $index >= self::WIDTH * self::WIDTH) {
            throw new Exception('超出范围');
        }
        $this->chunks[$index] = $chunk;
    }

    public function write() {
        $count = self::WIDTH * self::WIDTH;
        for ($i = 0; $i < $count; $i ++) {
            if (!isset($this->chunks[$i])) {
                $this->chunks[$i] = $this->load_chunk($i);
            }
        }

        $current_offset = 2;
        $offset_data = '';
        $time_data = '';

        for ($i = 0; $i < $count; $i ++) {
            if (!isset($this->chunks[$i]) || $this->chunks[$i] === null) {
                $offset_data .= pack('N', 0);
                $time_data .= pack('N', 0);
                continue;
            }
            [$time, $compress_type, $zip_data] = $this->chunks[$i]->get_data();

            $data_len = strlen($zip_data) + 1;
            $blocks = ceil(($data_len + 4) / self::BLOCK_SIZE);

            if ($blocks < 0 || $blocks > 255) {
                throw new Exception('数据长度超过限制');
            }

            $offset_data .= pack('N', $current_offset << 8 | $blocks);
            $time_data .= pack('N', $time);

            fseek($this->fh, $current_offset * self::BLOCK_SIZE, SEEK_SET);
            fwrite($this->fh, pack('N', $data_len) . pack('C', $compress_type) . $zip_data);

            $current_offset += $blocks;

            ftruncate($this->fh, $current_offset * self::BLOCK_SIZE);
        }

        fseek($this->fh, 0, SEEK_SET);
        fwrite($this->fh, $offset_data);
        fwrite($this->fh, $time_data);
    }

}
