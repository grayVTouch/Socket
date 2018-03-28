<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午10:21
 *
 * 业务逻辑处理进程
 */

return [
    // 当前自身监听地址
    'listen' => [
        // 主进程监听的地址
        // 用于建立与其他服务器的通讯
        'parent' => [
            // 协议: tcp udp websocket
            'protocol' => 'tcp' ,
            'ip'       => '0.0.0.0' ,
            'port'     => 9100
        ] ,
        // 子进程监听的地址
        // 用于建立与客户端的通信
        'child' => [
            'protocol' => 'tcp' ,
            'ip'       => '0.0.0.0' ,
            'port'     => 9101 ,
            // 进程数量
            'count'    => 1
        ]
    ] ,
    // 相关的通信服务器
    'server' => [
        // 协调服务器:通信地址
        // 用于检测服务器数量
        'register' => [
            // 通信必须是 tcp
            'protocol'  => 'tcp' ,
            'ip'        => '0.0.0.0' ,
            'port'      => 9102
        ]
    ] ,
];