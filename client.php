<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-21
 * Time: 上午11:58
 */

require_once './event/Event.php';
require_once './event/Select.php';

use Event\Event;
use Event\Select;

$s = new Select();

$tcp = 'tcp://127.0.0.1:9005';

// 作为客户端
$conn = stream_socket_client($tcp , $errno , $errstr);

$s->addIo($conn , Event::READ , function($socket){
    $msg = fread($socket , 65535);

    if (!empty($msg)) {
        var_dump($msg);
    }
});

fwrite($conn , '客户端发送给服务端的数据');

$s->loop();