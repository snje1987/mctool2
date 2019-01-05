<?php

namespace Org\Snje\MCTool;

use Org\Snje\MCTool;

class Loader {

    public static $len;

    public static function register() {
        self::$len = strlen(__NAMESPACE__ . '\\');
        spl_autoload_register([__NAMESPACE__ . '\Loader', 'class_loader']);
    }

    public static function class_loader($name) {
        if (strncmp(__NAMESPACE__ . '\\', $name, self::$len) !== 0) {
            return false;
        }
        $name = substr($name, self::$len);
        $file_path = __DIR__ . '/' . str_replace('\\', '/', $name) . '.php';
        if (file_exists($file_path) && is_readable($file_path)) {
            include($file_path);
            return true;
        }
        return false;
    }

}

MCTool\Loader::register();
