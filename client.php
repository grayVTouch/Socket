<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-4-2
 * Time: 下午4:39
 */

$tcp = 'tcp://127.0.0.1:9005';

$socket = stream_socket_client($tcp , $errno , $errstr);

sleep(2);

fwrite($socket , 'fuckyou');

sleep(2);
fclose($socket);