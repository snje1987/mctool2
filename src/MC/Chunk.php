<?php

namespace Org\Snje\MCTool\MC;

use Org\Snje\MCTool;
use Exception;

class Chunk {

    const WIDTH = 16;

    protected $compress_type;
    protected $zip_data;
    protected $time;

    public function __construct() {
        
    }

    public function set_data($time, $compress_type, $zip_data) {
        $this->compress_type = $compress_type;
        $this->zip_data = $zip_data;
        $this->time = $time;
    }

    public function set_nbt($nbt) {
        if ($this->compress_type == 1) {//gzip
            $this->zip_data = Region::encode_nbt($nbt, 'gzip');
        }
        elseif ($this->compress_type == 2) {
            $this->zip_data = Region::encode_nbt($nbt, 'zlib');
        }
        else {
            throw new Exception('数据不合法');
        }
    }

    public function get_data() {
        return [$this->time, $this->compress_type, $this->zip_data];
    }

    /**
     * @return MCTool\MC\NBT
     * @throws Exception
     */
    public function decode_nbt() {
        if ($this->compress_type == 1) {//gzip
            $nbt = Region::decode_nbt($this->zip_data, 'gzip');
        }
        elseif ($this->compress_type == 2) {
            $nbt = Region::decode_nbt($this->zip_data, 'zlib');
        }
        else {
            throw new Exception('数据不合法');
        }
        return $nbt;
    }

    public function print_nbt($out_file) {
        $nbt = $this->decode_nbt();
        $nbt->print($out_file);
    }

}
