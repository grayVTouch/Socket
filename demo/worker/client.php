<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-27
 * Time: 下午4:50
 */

$tcp = 'tcp://127.0.0.1:9105';

$conn = stream_socket_client($tcp , $errno , $errstr);

if (!$conn) {
    var_dump("链接失败");
}

fwrite($conn , 'fuckyou');
