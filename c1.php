<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午3:01
 */

$tcp = "tcp://192.168.150.135:9005";

$socket = stream_socket_client($tcp , $errno , $errstr);

sleep(2);

fwrite($socket , "fuckyou");