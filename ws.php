<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-21
 * Time: 下午2:06
 */

require_once './event/Event.php';
require_once './event/Select.php';
require_once './function/url.php';
require_once './function/array.php';
require_once './protocols/WebSocket.php';

use Event\Event;
use Event\Select;
use Protocols\WebSocket;

$tcp = 'tcp://127.0.0.1:9005';

$socket = stream_socket_server($tcp , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);

Select::addIo($socket , Event::READ , function($socket){
    $client = stream_socket_accept($socket);

    stream_set_blocking($client , false);

    // websocket 协议
    $ws = new WebSocket();

    $count = 1;

    // var_dump("接受到客户端链接");

    Select::addIo($client , Event::BOTH , function($client) use($ws , &$count){
        $msg = fread($client , 65535);

        if (!empty($msg)) {
            // var_dump($msg);
            // return ;

            // print_r('-------------' . PHP_EOL);
            // print_r('-------------' . PHP_EOL);

            // 输出客户端数据

            // print_r($msg);
            // print_r(PHP_EOL);

            // return ;
            // 检查握手状态
            if (!$ws->isShakeHand()) {
                echo "握手中...";

                $response = $ws->hand($msg);

                fwrite($client , $response);

                // print_r(PHP_EOL);
                // print_r(PHP_EOL);

                // print_r("$response);

                echo "握手成功,数据传输部分开启,请发送数据-------\n";

                Select::addSignal(SIGTERM , function() use(&$count , $client , $ws){
                    // 发送给客户端的数据
                    $count++;
                    $encode = $ws->encode("客户端你好啊,来自服务器的问候 {$count}");
                    fwrite($client , $encode);
                });

                Select::addSignal(SIGQUIT , function() use($client , $ws){
                    $ping = $ws->ping();

                    fwrite($client , $ping);
                });

                return ;
            }

            if ($ws->isPong($msg)) {
                var_dump("心跳检查客户端响应，证明客户端还活着！恭喜！！");
            } else if ($ws->isClose($msg)) {
                var_dump("客户端已关闭链接，服务器也应该关闭链接");
            } else {
                // 解析后的数据
                $client_msg = $ws->decode($msg);

                print_r("解码后数据:{$client_msg} ---- ");
                print_r("原生数据:{$msg}\n");

                if ($client_msg === '关闭s') {
                    echo "接收到客户端要求关闭的要求，关闭链接中 ...";
                    $close = $ws->close();

                    fwrite($client , $close);

                    echo "已向客户端发送关闭链接的请求，服务端关闭\n";
                    // unset($client);

                }
            }
        }
    });
});

Select::loop();