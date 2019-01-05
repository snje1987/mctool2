<?php

namespace Org\Snje\MCTool\MC;

use Org\Snje\MCTool;
use Exception;

class World {

    protected $dir;

    public static function format_coord($coord, $width = null) {
        if (is_array($coord)) {
            if (count($coord) != 2) {
                return [0, 0];
            }
            $x = $coord[0];
            $y = $coord[1];
        }
        elseif (is_string($coord)) {
            $coord = explode(',', $coord);
            if (count($coord) == 2) {
                $x = intval($coord[0]);
                $y = intval($coord[1]);
            }
            else {
                if ($width === null) {
                    return [0, 0];
                }
                $index = intval($coord[0]);
                $x = MCTool\App::mod($index, $width);
                $y = floor($index / $width);
            }
        }
        elseif (is_int($coord)) {
            if ($width === null) {
                return [0, 0];
            }
            $index = intval($coord);
            $x = MCTool\App::mod($index, $width);
            $y = floor($index / $width);
        }
        else {
            return [0, 0];
        }

        return [$x, $y];
    }

    public static function get_chunk_coord($block = null, $chunk = null) {
        if ($block !== null) {
            $coord = self::format_coord($block);
            return [floor($coord[0] / Chunk::WIDTH), floor($coord[1] / Chunk::WIDTH)];
        }
        elseif ($chunk !== null) {
            $coord = self::format_coord($chunk);
            return $coord;
        }
        else {
            return [0, 0];
        }
    }

    public function __construct($dir) {
        $this->dir = rtrim($dir, '/\\');
    }

    public function get_chunk($chunk_coord) {

        $file_name = 'r.' . floor($chunk_coord[0] / Region::WIDTH) . '.' . floor($chunk_coord[1] / Region::WIDTH) . '.mca';
        $full_path = $this->dir . '/' . $file_name;

        if (!file_exists($full_path)) {
            return null;
        }

        $region = new Region($full_path);
        $index = Region::get_chunk_index($chunk_coord);
        return $region->get_chunk($index);
    }

    /**
     * @param \Org\Snje\MCTool\RegionList $region_list
     * @param function $callback
     * @param mixed $args
     * @return mixed
     */
    public function walk($region_list, $callback, $args) {
        $ret = null;
        $files = scandir($this->dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (strlen($file) <= 4 || substr($file, -4) !== '.mca') {
                continue;
            }
            $file_region = Region::get_file_region($file);

            $file_region_list = $region_list->match($file_region);
            if ($file_region_list === null) {
                continue;
            }

            $file_region_list->transform(Region::WIDTH);

            $ret = call_user_func($callback, $file_region_list, $file, $args, $ret);
        }

        return $ret;
    }

    /**
     * @param \Org\Snje\MCTool\RegionList $region_list
     * @param string $filename
     * @param array $args
     * @param mixed $ret
     */
    public function copy_chunk($region_list, $filename, $args, $ret) {
        echo $filename . "\n";

        $src_file = MCTool\App::join_path($this->dir, $filename);
        $dst_file = MCTool\App::join_path($args['dst'], $filename);

        $from = new Region($src_file);
        $to = new Region($dst_file, true);

        $from->walk($region_list, [$from, 'copy_chunk'], $to);

        $to->write();

        unset($from);
        unset($to);
    }

    /**
     * @param \Org\Snje\MCTool\RegionList $region_list
     * @param string $filename
     * @param array $task
     * @param mixed $ret
     */
    public function reset_trade($region_list, $filename, $task, $ret) {
        echo $filename . "\n";

        $src_file = MCTool\App::join_path($this->dir, $filename);

        $from = new Region($src_file);

        $from->walk($region_list, [$from, 'reset_trade'], $task);

        $from->write();

        unset($from);
    }

}
