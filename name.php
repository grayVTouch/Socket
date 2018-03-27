<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午10:36
 */

require_once './event/Event.php';
require_once './event/Select.php';
require_once './Function/base.php';

use Event\Event;
use Event\Select;

$tcp = 'tcp://127.0.0.1:9005';

$socket = stream_socket_server($tcp , $errno , $errstr);

if (!$socket) {
    throw new Exception("创建 socket 服务端失败");
}

Select::addIo($socket , Event::READ , function($socket){
    $client = stream_socket_accept($socket);

    // 获取地址
    var_dump(stream_socket_get_name($client , true));
    var_dump(stream_socket_get_name($client , false));
});

Select::loop();