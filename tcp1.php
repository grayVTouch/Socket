<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午5:18
 */
require_once './function/array.php';
require_once './event/Event.php';
require_once './event/Select.php';


use Event\Event;
use Event\Select;

$tcp = '127.0.0.1:9005';

$socket = stream_socket_server($tcp);

Select::addIo($socket , Event::READ , function($socket){
    $client = stream_socket_accept($socket);

    var_dump("127.0.0.1:9005: 接受到客户端链接");

    Select::addIo($client , Event::READ , function($socket){
        $msg = fread($socket , 65535);

        if (!empty($msg)) {
            var_dump("127.0.0.1:9005" . $msg);
        }
    });
});

Select::loop();