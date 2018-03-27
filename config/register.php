<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午9:40
 *
 * 注册服务器处理进程配置文件
 */

// 一定是单台服务器

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
            'protocol' => 'websocket' ,
            'ip'       => '0.0.0.0' ,
            'port'     => 9101 ,
            // 进程数量
            'count'    => 1
        ]
    ] ,
    // 针对客户端的设置
    'client' => [
    // 单位:秒
    'heart_time' => 2
]
];