<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午10:02
 */

// 根据配置文件生成通讯地址
function gen_address($data){
    return "{$data['protocol']}://{$data['ip']}:{$data['port']}";
}

// 获取应用实例
function app(){
    return $GLOBALS['app'];
}

// 获取系统配置文件
function config($k , array $args = []){
    return $GLOBALS['app']->config($k , $args);
}