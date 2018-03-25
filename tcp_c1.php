<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午5:25
 */

$tcp = '127.0.0.1:9002';

$conn = stream_socket_client($tcp);

sleep(2);

fwrite($conn , 'hello world!');