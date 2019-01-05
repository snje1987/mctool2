<?php

namespace Org\Snje\MCTool;

use Org\Snje\MCTool;
use Exception;

class RegionList {

    protected $list = [];

    public static function match_range($left, $right) {
        $count_l = count($left);
        if ($count_l < 2) {
            return $right;
        }
        $l = max($left[0], $right[0]);
        $r = min($left[1], $right[1]);

        if ($l <= $r) {
            return [$l, $r];
        }

        return null;
    }

    public static function match_region($region, $to_match) {
        $x = self::match_range($region['x'], $to_match['x']);
        $z = self::match_range($region['z'], $to_match['z']);
        if ($x === null || $z === null) {
            return null;
        }

        $ret = [];
        $ret['type'] = $region['type'];
        $ret['x'] = $x;
        $ret['z'] = $z;
        return $ret;
    }

    public static function format_range($range, $scale = 1) {
        if (!is_array($range)) {
            return [];
        }

        $count = count($range);
        if ($count < 1) {
            return [];
        }
        elseif ($count == 1) {
            $ret = [$range[0], $range[0]];
        }
        elseif ($range[0] <= $range[1]) {
            $ret = [$range[0], $range[1]];
        }
        else {
            $ret = [$range[1], $range[0]];
        }

        $ret[0] = floor($ret[0] / $scale);
        $ret[1] = floor($ret[1] / $scale);
        return $ret;
    }

    public static function transform_range($range, $scale) {
        $range[0] = MCTool\App::mod($range[0], $scale);
        $range[1] = MCTool\App::mod($range[1], $scale);
        return $range;
    }

    public function __construct() {
        
    }

    public function add($regions, $scale = 1) {
        foreach ($regions as $region) {
            $this->add_region($region, $scale);
        }
    }

    public function add_region($region, $scale = 1) {
        $data = [];
        $data['type'] = isset($region['type']) ? strval($region['type']) : 'include';

        if (isset($region['x'])) {
            $data['x'] = self::format_range($region['x'], $scale);
        }
        else {
            $data['x'] = [];
        }

        if (isset($region['z'])) {
            $data['z'] = self::format_range($region['z'], $scale);
        }
        else {
            $data['z'] = [];
        }

        $this->list[] = $data;
    }

    /**
     * @param array $to_match
     * @return \Org\Snje\MCTool\RegionList
     */
    public function match($to_match) {
        $ret_list = [];
        foreach ($this->list as $region) {
            $result = self::match_region($region, $to_match);
            if ($result !== null) {
                $ret_list[] = $result;
            }
        }
        if (count($ret_list) <= 0) {
            return null;
        }

        $region_list = new self();
        $region_list->add($ret_list);
        return $region_list;
    }

    public function transform($scale) {
        foreach ($this->list as $k => $v) {
            if (!empty($v['x'])) {
                $v['x'] = self::transform_range($v['x'], $scale);
            }
            if (!empty($v['z'])) {
                $v['z'] = self::transform_range($v['z'], $scale);
            }
            $this->list[$k] = $v;
        }
    }

    public function get_chunk_list() {
        $ret = [];

        foreach ($this->list as $region) {
            $x_range = $region['x'];
            $z_range = $region['z'];
            $rtype = $region['type'];
            for ($x = $x_range[0]; $x <= $x_range[1]; $x ++) {
                for ($z = $z_range[0]; $z <= $z_range[1]; $z ++) {
                    if ($rtype == 'include') {
                        $ret[$x + $z * MC\Region::WIDTH] = 1;
                    }
                    else {
                        $ret[$x + $z * MC\Region::WIDTH] = 0;
                    }
                }
            }
        }
        return $ret;
    }

    public function __toString() {
        $data = json_encode($this->list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $data;
    }

}
