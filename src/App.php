<?php

namespace Org\Snje\MCTool;

use Org\Snje\MCTool;
use Exception;

class App {

    protected $cfg = [];
    protected $argv;
    protected $cmd = null;
    protected static $cmds = [
        'nbt', 'copy', 'list', 'help', 'reset_trade'
    ];

    public static function join_path($path1, $path2) {
        $path1 = rtrim($path1) . '/';
        if (DIRECTORY_SEPARATOR === '/') {
            if ($path2{0} !== '/') {
                $path = $path1 . $path2;
            }
        }
        else {
            if (!preg_match('^[a-zA-Z]:.*$', $path2)) {
                $path = $path1 . $path2;
            }
        }
        return str_replace('\\', '/', $path);
    }

    public static function mod($left, $right) {
        $ret = $left % $right;
        if ($ret < 0) {
            $ret += $right;
        }
        return $ret;
    }

    public function __construct() {
        
    }

    public function run($argc, $argv) {
        try {
            array_shift($argv);
            $this->argv = $argv;
            $this->parse_args();
            $this->do_job();
        }
        catch (Exception $ex) {
            echo $ex->getFile() . '[' . $ex->getLine() . ']: ' . $ex->getMessage() . "\n";
            $this->print_help();
        }
    }

    protected function print_help() {
        echo <<< DOC
usage: mctool CMD OPTIONS

CMD
    nbt: display nbt info.

        OPTIONS
        -f --file: input file path, -f and -d cannot be all empty.
        -d --dir: region file dir, -f and -d cannot be all empty.
        -C --chunk: chunk coord of the chunk to decode.
        -B --block: block coord, the chunk contain the block will be decode.
        -O --output: out put file, will be set to stdout if not specified.
        --compress: can be zlib or gzip, compress type of the file, default to zlib.

    list: list exists chunks in a region file.

        OPTIONS
        -f --file: the region file to decode.
        -O --output: out put file, will be set to stdout if not specified.

    copy: copy selected chunks in region file to a new one.

        OPTIONS
        -c --config: the config file path.

        CONFIG FILE FORMAT
        {
            "src": "aaa/bbb", //path of the src region file's dir.
            "dst": "ccc/ddd", //path of the dst region file's dir.
            "area": [ //aria list, chunks in these aras will be processed. list will be processed topdown, previous one will be override.
                {
                    "type": "include", //area type, can be include or exclude.
                    "x": [-177, -344], //x range, in block, and the entire chunk will be copied.
                    "z": [-439, -312] //z range, in block, and the entire chunk will be copied.
                },
                {
                    "type": "include",
                    "x": [-307, -177],
                    "z": [-481, -412]
                }
            ]
        }

    reset_trade: reset villager's trade.

        OPTIONS
        -c --config: the config file path.

        CONFIG FILE FORMAT
        {
            "src": "aaa/bbb", //path of the src region file's dir.
            "list": [ //villagers to be processed.
                {
                    "area": [
                        {
                            type": "include", //area type, can be include or exclude.
                            "x": [-307, -177], //x range, in block, and all the villagers in the chunk will be processed.
                            "z": [-481, -412] //z range, in block, and all the villagers in the chunk will be processed.
                        }
                    ],
                    "name": "trader", //optional, the CustomName of villagers to be processed.
                    "do": {
                        "max_uses": 1000000, //set maxUses.
                        "uses": 0 //set uses.
                    }
                }
            ]
        }

    help: display this message.



DOC;
    }

    protected function parse_args() {
        if (empty($this->argv)) {
            throw new Exception('命令不能为空');
        }

        $this->cmd = $this->argv[0];
        array_shift($this->argv);

        if (!in_array($this->cmd, self::$cmds)) {
            throw new Exception('命令不存在');
        }

        while (true) {
            if (empty($this->argv)) {
                break;
            }
            $arg = array_shift($this->argv);

            if (substr($arg, 0, 2) === '--') {
                $arg_name = $arg;
                $pos = strpos($arg, '=');
                if ($pos !== false) {
                    $arg_value = substr($arg, $pos + 1);
                    $arg_name = substr($arg, 0, $pos);
                    array_unshift($this->argv, $arg_value);
                }
            }
            elseif (substr($arg, 0, 1) === '-') {
                $arg_name = substr($arg, 0, 2);
                if (strlen($arg) > 2) {
                    if ($arg{2} === '=') {
                        $arg_value = substr($arg, 3);
                    }
                    else {
                        $arg_value = substr($arg, 2);
                    }
                    array_unshift($this->argv, $arg_value);
                }
            }
            else {
                throw new Exception('参数不正确:' . $arg . ' ' . implode(' ', $this->argv));
            }

            $this->fill_cfg($arg_name);
        }
    }

    protected function fill_cfg($arg_name) {
        switch ($arg_name) {
            case '-d':
            case '--dir':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('目录路径不能为空');
                }
                $this->cfg['dir'] = $value;
                break;
            case '-c':
            case '--config':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('配置路径不能为空');
                }
                $this->cfg['config'] = $value;
                break;
            case '-f':
            case '--file':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('文件不能为空');
                }
                $this->cfg['file'] = $value;
                break;
            case '-O':
            case '--output':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('输出路径不能为空');
                }
                $this->cfg['output'] = $value;
                break;
            case '-C':
            case '--chunk':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('区块坐标不能为空');
                }
                $this->cfg['chunk'] = $value;
                break;
            case '-B':
            case '--block':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('方块坐标不能为空');
                }
                $this->cfg['block'] = $value;
                break;
            case '--compress':
                $value = $this->get_arg_value();
                if ($value === false) {
                    throw new Exception('压缩方式不能为空');
                }
                $this->cfg['compress'] = $value;
                break;
            default :
                throw new Exception('参数不正确');
        }
    }

    protected function get_arg_value() {
        if (empty($this->argv)) {
            return false;
        }
        return array_shift($this->argv);
    }

    protected function do_job() {
        switch ($this->cmd) {
            case 'nbt'://显示nbt信息
                $this->do_nbt();
                break;
            case 'copy'://清理区块
                $this->do_copy();
                break;
            case 'list'://列出已生成区块
                $this->do_list();
                break;
            case 'reset_trade': //重置村民交易计数
                $this->do_reset_trade();
                break;
            case 'help':
                $this->print_help();
                break;
            default :
                throw new Exception('命令不存在');
        }
    }

    protected function do_nbt_file($file, $out_file) {
        if (isset($this->cfg['chunk']) || isset($this->cfg['block'])) {

            if (isset($this->cfg['block'])) {
                $chunk_coord = MC\World::get_chunk_coord($this->cfg['block']);
                $chunk_index = MC\Region::get_chunk_index($chunk_coord);
            }
            else {
                $chunk_index = MC\Region::get_chunk_index($this->cfg['chunk']);
            }
            $region = new MC\Region($file);
            $chunk = $region->get_chunk($chunk_index);
            if ($chunk !== null) {
                $chunk->print_nbt($out_file);
            }
            else {
                throw new Exception('区块不存在');
            }
        }
        else {
            $data = file_get_contents($file);
            $compress = 'zlib';
            if (!empty($this->cfg['compress'])) {
                $compress = $this->cfg['compress'];
            }
            $nbt = MC\Region::decode_nbt($data, $compress);
            if (!$nbt !== null) {
                $nbt->print($out_file);
            }
            else {
                throw new Exception('文件格式错误');
            }
        }
    }

    protected function do_nbt_dir($dir, $out_file) {
        if (isset($this->cfg['block'])) {
            $coord = MC\World::get_chunk_coord($this->cfg['block']);
        }
        elseif (isset($this->cfg['chunk'])) {
            $coord = MC\World::get_chunk_coord(null, $this->cfg['chunk']);
        }

        $world = new MC\World($dir);
        $chunk = $world->get_chunk($coord);

        if ($chunk !== null) {
            $chunk->print_nbt($out_file);
        }
        else {
            throw new Exception('区块不存在');
        }
    }

    protected function do_nbt() {
        $out_file = STDOUT;
        if (isset($this->cfg['output'])) {
            $out_file = fopen($this->cfg['output'], 'wb');
        }
        if (isset($this->cfg['file'])) {
            $this->do_nbt_file($this->cfg['file'], $out_file);
        }
        elseif (isset($this->cfg['dir'])) {
            $this->do_nbt_dir($this->cfg['dir'], $out_file);
        }
        else {
            throw new Exception('必须指定文件路径');
        }
        if ($out_file !== STDOUT) {
            fclose($out_file);
        }
    }

    protected function load_cfg($name) {
        if (!isset($this->cfg[$name])) {
            throw new Exception('未指定配置文件');
        }
        $path = $this->cfg[$name];
        if (!is_file($path)) {
            throw new Exception('配置文件不存在');
        }

        $cwd = getcwd();

        $cfg_path = self::join_path($cwd, $path);
        $str = file_get_contents($cfg_path);
        $json = json_decode($str, true);

        if ($json !== null) {
            if (isset($json['src'])) {
                $json['src'] = self::join_path(dirname($cfg_path), $json['src']);
            }
            if (isset($json['dst'])) {
                $json['dst'] = self::join_path(dirname($cfg_path), $json['dst']);
            }
        }

        return $json;
    }

    protected function do_copy() {
        $config = $this->load_cfg('config');
        if ($config === null) {
            throw new Exception('配置文件不合法');
        }

        if (!isset($config['src']) || !isset($config['dst'])) {
            throw new Exception('缺少必要项目');
        }
        if (!isset($config['area']) || empty($config['area'])) {
            throw new Exception('缺少区域列表');
        }

        $region_list = new RegionList();
        $region_list->add($config['area'], MC\Chunk::WIDTH);

        $dst = $config['dst'];
        if (file_exists($dst)) {
            if (!is_dir($dst)) {
                throw new Exception('输出路径不是目录');
            }
        }
        else {
            mkdir($dst, 0755, true);
        }

        $world = new MC\World($config['src']);
        $world->walk($region_list, [$world, 'copy_chunk'], [
            'dst' => $config['dst'],
        ]);
    }

    protected function do_reset_trade() {
        $config = $this->load_cfg('config');
        if ($config === null) {
            throw new Exception('配置文件不合法');
        }

        if (!isset($config['src'])) {
            throw new Exception('缺少必要项目');
        }

        if (!isset($config['list']) || empty($config['list'])) {
            throw new Exception('未指定要进行的操作');
        }

        $list = $config['list'];

        $world = new MC\World($config['src']);

        foreach ($list as $task) {
            if (empty($task['area'])) {
                throw new Exception('缺少区域列表');
            }
            $region_list = new RegionList();
            $region_list->add($task['area'], MC\Chunk::WIDTH);

            $world->walk($region_list, [$world, 'reset_trade'], $task);
        }
    }

    protected function do_list() {
        if (!isset($this->cfg['file'])) {
            throw new Exception('必须指定文件路径');
        }
        $out_file = STDOUT;
        if (isset($this->cfg['output'])) {
            $out_file = fopen($this->cfg['output'], 'wb');
        }

        $region = new MC\Region($this->cfg['file']);
        $region->show_chunks($out_file);

        if ($out_file !== STDOUT) {
            fclose($out_file);
        }
    }

}
