<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-30
 * Time: 下午2:05
 */

$tcp = 'tcp://127.0.0.1:9005';
$socket = stream_socket_client($tcp , $errno , $errstr);

stream_set_blocking($socket , false);

fwrite($socket , '');

$i = 0;
while ($i < 5)
{
    $i++;
    var_dump("等待中" . $i);
    sleep(1);
}

fwrite($socket , 'fuckyou');

fclose($socket);
