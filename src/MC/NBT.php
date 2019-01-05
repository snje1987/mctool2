<?php

namespace Org\Snje\MCTool\MC;

use Org\Snje\MCTool;
use Exception;

/**
 * Class for reading in NBT-format files.
 *
 * @author  Justin Martin <frozenfire@thefrozenfire.com>
 * @modify Yang Ming <yangming0116@gmail.com>
 * @version 1.0
 *
 * Dependencies:
 *  PHP 4.3+ (5.3+ recommended)
 *  GMP Extension
 */
class NBT {

    public $root = [];
    public $verbose = false;

    const TAG_END = 0;
    const TAG_BYTE = 1;
    const TAG_SHORT = 2;
    const TAG_INT = 3;
    const TAG_LONG = 4;
    const TAG_FLOAT = 5;
    const TAG_DOUBLE = 6;
    const TAG_BYTE_ARRAY = 7;
    const TAG_STRING = 8;
    const TAG_LIST = 9;
    const TAG_COMPOUND = 10;
    const TAG_INT_ARRAY = 11;
    const TAG_LONG_ARRAY = 12;

    public function print($fp, $node = null, $level = 0, $type = '') {
        if ($node === null) {
            $node = $this->root[''];
        }
        $indent = '    ';
        $prefix = str_repeat($indent, $level);

        if (empty($type)) {
            $type = $node['type'];
            $value = $node['value'];
        }
        else {
            $value = $node;
        }

        $type = empty($type) ? $node['type'] : $type;
        switch ($type) {
            case self::TAG_COMPOUND:
                fwrite($fp, "{\n");
                $len = count($value);
                $k = 0;
                foreach ($value as $sub_name => $sub_value) {
                    $sub_name = str_replace('"', '\\"', $sub_name);
                    fwrite($fp, "{$prefix}{$indent}\"{$sub_name}\" : ");
                    $this->print($fp, $sub_value, $level + 1);
                    if (++$k < $len) {
                        fwrite($fp, ",");
                    }
                    fwrite($fp, "\n");
                }
                fwrite($fp, "{$prefix}}");
                break;
            case self::TAG_BYTE:
                fwrite($fp, "\"{$value} b\"");
                break;
            case self::TAG_SHORT:
                fwrite($fp, "\"{$value} s\"");
                break;
            case self::TAG_INT:
                //fwrite($fp, print_r($value, true));
                fwrite($fp, "\"{$value} i\"");
                break;
            case self::TAG_LONG:
                fwrite($fp, "\"{$value} l\"");
                break;
            case self::TAG_FLOAT:
                fwrite($fp, "\"{$value} f\"");
                break;
            case self::TAG_DOUBLE:
                fwrite($fp, "\"{$value} d\"");
                break;
            case self::TAG_BYTE_ARRAY:
                $len = count($value);
                fwrite($fp, "\"Byte Array($len)\"");
                break;
            case self::TAG_STRING:
                $value = str_replace('"', '\\"', $value);
                fwrite($fp, "\"{$value}  \"");
                break;
            case self::TAG_LIST:
                fwrite($fp, "[\n");

                $value = $value;
                $sub_type = $value['type'];
                $len = count($value['value']);

                foreach ($value['value'] as $index => $sub_value) {
                    fwrite($fp, "{$prefix}{$indent}");
                    $this->print($fp, $sub_value, $level + 1, $sub_type);
                    if ($index < $len - 1) {
                        fwrite($fp, ",");
                    }
                    fwrite($fp, "\n");
                }

                fwrite($fp, "{$prefix}]");
                break;
            case self::TAG_INT_ARRAY:
                $len = count($value);
                fwrite($fp, "\"Int Array($len)\"");
                break;
            case self::TAG_LONG_ARRAY:
                $len = count($value);
                fwrite($fp, "\"Long Array($len)\"");
                break;
        }
    }

    public function load($fp) {
        $this->traverseTag($fp, $this->root);
    }

    public function writeFile($filename, $wrapper = "compress.zlib://") {
        if (is_string($wrapper)) {
            $fp = fopen("{$wrapper}{$filename}", "wb");
        }
        elseif (is_null($wrapper) && is_resource($fp)) {
            $fp = $filename;
        }
        else {
            throw new Exception('输出文件不存在');
        }
        foreach ($this->root as $rootNum => $rootTag) {
            if (!$this->writeTag($fp, $rootTag)) {
                throw new Exception('写入文件失败');
            }
        }
        return true;
    }

    public function purge() {
        $this->root = [];
    }

    public function traverseTag($fp, &$tree) {
        if (feof($fp)) {
            return false;
        }
        $tagType = $this->readType($fp, self::TAG_BYTE); // Read type byte.
        if ($tagType == self::TAG_END) {
            return false;
        }
        else {
            $tagName = $this->readType($fp, self::TAG_STRING);
            $tagData = $this->readType($fp, $tagType);
            $tree[$tagName] = ["type" => $tagType, "value" => $tagData];
            return true;
        }
    }

    public function writeTag($fp, $tag) {
        return $this->writeType($fp, self::TAG_BYTE, $tag["type"]) && $this->writeType($fp, self::TAG_STRING, $tag["name"]) && $this->writeType($fp, $tag["type"], $tag["value"]);
    }

    public function readType($fp, $tagType) {
        switch ($tagType) {
            case self::TAG_BYTE: // Signed byte (8 bit)
                list(, $unpacked) = unpack("c", fread($fp, 1));
                return $unpacked;
            case self::TAG_SHORT: // Signed short (16 bit, big endian)
                list(, $unpacked) = unpack("n", fread($fp, 2));
                if ($unpacked >= pow(2, 15)) {
                    $unpacked -= pow(2, 16);
                } // Convert unsigned short to signed short.
                return $unpacked;
            case self::TAG_INT: // Signed integer (32 bit, big endian)
                list(, $unpacked) = unpack("N", fread($fp, 4));
                if ($unpacked >= pow(2, 31)) {
                    $unpacked -= pow(2, 32);
                } // Convert unsigned int to signed int
                return $unpacked;
            case self::TAG_LONG: // Signed long (64 bit, big endian)
                list(, $firstHalf) = unpack("N", fread($fp, 4));
                list(, $secondHalf) = unpack("N", fread($fp, 4));
                $value = gmp_add($secondHalf, gmp_mul($firstHalf, "4294967296"));
                if (gmp_cmp($value, gmp_pow(2, 63)) >= 0) {
                    $value = gmp_sub($value, gmp_pow(2, 64));
                }
                return gmp_strval($value);
            case self::TAG_FLOAT: // Floating point value (32 bit, big endian, IEEE 754-2008)
                list(, $value) = (pack('d', 1) == "\77\360\0\0\0\0\0\0") ? unpack('f', fread($fp, 4)) : unpack('f', strrev(fread($fp, 4)));
                return $value;
            case self::TAG_DOUBLE: // Double value (64 bit, big endian, IEEE 754-2008)
                list(, $value) = (pack('d', 1) == "\77\360\0\0\0\0\0\0") ? unpack('d', fread($fp, 8)) : unpack('d', strrev(fread($fp, 8)));
                return $value;
            case self::TAG_BYTE_ARRAY: // Byte array
                $arrayLength = $this->readType($fp, self::TAG_INT);
                $array = [];
                for ($i = 0; $i < $arrayLength; $i++) {
                    $array[] = $this->readType($fp, self::TAG_BYTE);
                }
                return $array;
            case self::TAG_STRING: // String
                if (!$stringLength = $this->readType($fp, self::TAG_SHORT)) {
                    return "";
                }
                $string = fread($fp, $stringLength); // Read in number of bytes specified by string length, and decode from utf8.
                return $string;
            case self::TAG_LIST: // List
                $tagID = $this->readType($fp, self::TAG_BYTE);
                $listLength = $this->readType($fp, self::TAG_INT);
                $list = ["type" => $tagID, "value" => []];
                for ($i = 0; $i < $listLength; $i++) {
                    if (feof($fp)) {
                        break;
                    }
                    $list["value"][] = $this->readType($fp, $tagID);
                }
                return $list;
            case self::TAG_COMPOUND: // Compound
                $tree = [];
                while ($this->traverseTag($fp, $tree));
                return $tree;
            case self::TAG_INT_ARRAY: // Byte array
                $arrayLength = $this->readType($fp, self::TAG_INT);
                $array = [];
                for ($i = 0; $i < $arrayLength; $i++) {
                    $array[] = $this->readType($fp, self::TAG_INT);
                }
                return $array;
            case self::TAG_LONG_ARRAY: // Byte array
                $arrayLength = $this->readType($fp, self::TAG_INT);
                $array = [];
                for ($i = 0; $i < $arrayLength; $i++) {
                    $array[] = $this->readType($fp, self::TAG_LONG);
                }
                return $array;
        }
    }

    public function writeType($fp, $tagType, $value) {
        switch ($tagType) {
            case self::TAG_BYTE: // Signed byte (8 bit)
                return is_int(fwrite($fp, pack("c", $value)));
            case self::TAG_SHORT: // Signed short (16 bit, big endian)
                if ($value < 0) {
                    $value += pow(2, 16);
                } // Convert signed short to unsigned short
                return is_int(fwrite($fp, pack("n", $value)));
            case self::TAG_INT: // Signed integer (32 bit, big endian)
                if ($value < 0) {
                    $value += pow(2, 32);
                } // Convert signed int to unsigned int
                return is_int(fwrite($fp, pack("N", $value)));
            case self::TAG_LONG: // Signed long (64 bit, big endian)
                $secondHalf = gmp_mod($value, 2147483647);
                $firstHalf = gmp_sub($value, $secondHalf);
                return is_int(fwrite($fp, pack("N", gmp_intval($firstHalf)))) && is_int(fwrite($fp, pack("N", gmp_intval($secondHalf))));
            case self::TAG_FLOAT: // Floating point value (32 bit, big endian, IEEE 754-2008)
                return is_int(fwrite($fp, (pack('d', 1) == "\77\360\0\0\0\0\0\0") ? pack('f', $value) : strrev(pack('f', $value))));
            case self::TAG_DOUBLE: // Double value (64 bit, big endian, IEEE 754-2008)
                return is_int(fwrite($fp, (pack('d', 1) == "\77\360\0\0\0\0\0\0") ? pack('d', $value) : strrev(pack('d', $value))));
            case self::TAG_BYTE_ARRAY: // Byte array
                return $this->writeType($fp, self::TAG_INT, count($value)) && is_int(fwrite($fp, call_user_func_array("pack", array_merge(["c" . count($value)], $value))));
            case self::TAG_STRING: // String
                $value = utf8_encode($value);
                return $this->writeType($fp, self::TAG_SHORT, strlen($value)) && is_int(fwrite($fp, $value));
            case self::TAG_LIST: // List
                if (!($this->writeType($fp, self::TAG_BYTE, $value["type"]) && $this->writeType($fp, self::TAG_INT, count($value["value"])))) {
                    return false;
                }
                foreach ($value["value"] as $listItem) {
                    if (!$this->writeType($fp, $value["type"], $listItem)) {
                        return false;
                    }
                }
                return true;
            case self::TAG_COMPOUND: // Compound
                foreach ($value as $listItem) {
                    if (!$this->writeTag($fp, $listItem)) {
                        return false;
                    }
                }
                if (!is_int(fwrite($fp, "\0"))) {
                    return false;
                }
                return true;
            case self::TAG_INT_ARRAY: // Byte array
                return $this->writeType($fp, self::TAG_INT, count($value)) && is_int(fwrite($fp, call_user_func_array("pack", array_merge(["N" . count($value)], $value))));
            case self::TAG_LONG_ARRAY: // Byte array
                if (!$this->writeType($fp, self::TAG_INT, count($value))) {
                    return false;
                }
                foreach ($value as $v) {
                    $secondHalf = gmp_mod($v, 2147483647);
                    $firstHalf = gmp_sub($v, $secondHalf);
                    if (!(
                            is_int(fwrite($fp, pack("N", gmp_intval($firstHalf)))) &&
                            is_int(fwrite($fp, pack("N", gmp_intval($secondHalf))))
                            )) {
                        return false;
                    }
                }
        }
    }

}
