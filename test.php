<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-21
 * Time: 上午11:18
 */

require_once './event/Event.php';
require_once './event/Select.php';

use Event\Event;
use Event\Select;

$s = new Select();

$s->addTimer(1 , true , function(){
    var_dump("2s 后触发的定时器");
});

$s->addTimer(4 , true , function(){
    var_dump("4s 后触发的定时器");
});

$s->addSignal(SIGTERM , function(){
    exit("接收到进程退出的信号,进程正常退出\n");
});

// 添加 socket 事件
$tcp = 'tcp://127.0.0.1:9005';

$server = stream_socket_server($tcp , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);

$s->addIo($server , Event::READ , function($socket) use($s){
    $client = stream_socket_accept($socket);

    var_dump('接收到客户端链接');

    stream_set_blocking($client , false);

    $s->addIo($client , Event::BOTH , function() use($client){
        $msg = fread($client , 65535);

        if (!empty($msg)) {
            var_dump($msg);
        }
    });
});

var_dump("开始跑事件监听");

$s->loop();