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
require_once './protocols/WebSocket.php';

use Event\Event;
use Event\Select;
use Protocols\WebSocket;

$tcp = 'tcp://127.0.0.1:9005';

$socket = stream_socket_server($tcp , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);

$s = new Select();

$s->addIo($socket , Event::READ , function($socket) use($s){
    $client = stream_socket_accept($socket);

    stream_set_blocking($client , false);

    // websocket 协议
    $ws = new WebSocket();

    $count = 1;

    $s->addIo($client , Event::BOTH , function($client) use($s , $ws , &$count){
        $msg = fread($client , 65535);

        if (!empty($msg)) {
            // print_r($msg);
            // print_r('-------------' . PHP_EOL);
            // print_r('-------------' . PHP_EOL);

            // 输出客户端数据


            // 检查握手状态
            if (!$ws->isShakeHand()) {
                echo "握手中...";

                $response = $ws->hand($msg);

                fwrite($client , $response);

                // print_r(PHP_EOL);
                // print_r(PHP_EOL);

                // print_r("$response);

                echo "握手成功,数据传输部分开启,请发送数据-------\n";

                $s->addSignal(SIGTERM , function() use(&$count , $client , $ws){
                    // 发送给客户端的数据
                    $count++;
                    $encode = $ws->encode("客户端你好啊,来自服务器的问候 {$count}");
                    fwrite($client , $encode);
                });

                return ;
            }

            // 解析后的数据
            $client_msg = $ws->decode($msg);

            print_r("解码后数据:{$client_msg} ---- ");
            print_r("原生数据:{$msg}\n");
        }
    });
});

$s->loop();

