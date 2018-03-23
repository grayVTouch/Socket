<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午2:54
 */

require_once './function/url.php';
require_once './function/array.php';
require_once './event/Event.php';
require_once './event/Select.php';

use Event\Event;
use Event\Select;


// tcp 链接
$tcp = 'tcp://127.0.0.1:9005';

// 通过域名的方式实现 socket 的负载均衡
$socket = stream_socket_server($tcp , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);

$s = new Select();

$s->addIo($socket , Event::READ , function($socket) use($s){
    $client = stream_socket_accept($socket);

    stream_set_blocking($client , false);

    var_dump("接受到客户端链接");

    $s->addIo($client , Event::READ , function($client){
        $msg = fread($client , 65535);

        if (!empty($msg)) {
            var_dump($msg);
        }
    });
});

$s->loop();



