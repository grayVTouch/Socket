<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2017/11/26
 * Time: 23:05
 */

namespace Core;


class Autoload
{
    protected static $_suffix = '.php';

    function __construct(){
        spl_autoload_register(function($class){
            $file = ROOT_DIR . lcfirst($class) . self::$_suffix;
            $file = str_replace('\\' , '/' , $file);

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    // 类自动加载
    public static function classLoader($namespace , $dir){
        spl_autoload_register(function($class) use($namespace , $dir){
            $class = preg_replace("/(\\\\)?{$namespace}\/" , '' , $class);
            $file  = str_replace('\\' , '/' , $dir . $class);
            $file .= self::$_suffix;

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    // 文件载入
    public static function fileLoader($file){
        if (file_exists($file)) {
            require_once str_replace('\\' , '/' , $file);
        }
    }

    // 注册
    public static function register($register){
        foreach ($register as $k => $v)
        {
            if ($k === 'class') {
                foreach ($v as $k1 => $v1)
                {
                    self::classLoader($k1 , $v1);
                }
            } else if ($k === 'file') {
                foreach ($v as $v1)
                {
                    self::fileLoader($v1);
                }
            } else {
                // 预留...
            }
        }
    }
}