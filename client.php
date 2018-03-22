<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-21
 * Time: 上午11:58
 */

$tcp = 'tcp://127.0.0.1:9005';

$conn = stream_socket_client($tcp);

sleep(2);

fwrite($conn , '客户端发送给服务端的测试数据');