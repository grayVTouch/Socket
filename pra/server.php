<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-30
 * Time: 下午2:05
 */

$tcp = 'tcp://127.0.0.1:9005';

$context = stream_context_create([
    'socket' => [
        'backlog' => 10000 ,
        'so_reuseport' => true
    ]
]);

$server = stream_socket_server($tcp , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN , $context);
$read = [$server];
$write = $read;
$except = [];
$wait_s = 0;
$wait_ns = 0;

while (true)
{
    $read_c = $read;
    $write_c = $write;

    stream_select($read_c , $write_c , $except , $wait_s , $wait_ns);

    foreach ($read_c as $v)
    {
        if ($v === $server) {
            $client = stream_socket_accept($v);

            stream_set_blocking($client , false);

            $read[] = $client;
            $write[] = $client;
        } else {
            // 一旦连接断开后，便会无线重复触发消息可读取事件
            $msg = fread($v , 65535);



            var_dump("接收到客户端数据{$msg}");
        }
    }

    usleep(1);
}