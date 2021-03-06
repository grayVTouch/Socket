<?php
/**
 * 转发服务器处理进程配置文件
 * 注意端口的正确设置!!
 */

return [
    // 是否启用 Register
    'enable_register' => true ,

    // 是否启用 worker
    'enable_worker' => true ,

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
    // 相关的通信服务器
    'server' => [
        // 协调服务器:通信地址
        // 用于检测服务器数量
        'register' => [
            // 通信必须是 tcp
            'protocol'  => 'tcp' ,
            'ip'        => '0.0.0.0' ,
            'port'      => 9102
        ] ,

        // 业务处理服务器:通信地址
        // 通常是代理服务器的通信地址(因为要做负载均衡)
        // 通信协议必须是 tcp 协议
        'worker' => [
            'protocol'  => 'tcp' ,
            'ip'        => '0.0.0.0' ,
            'port'      => 9103
        ]
    ] ,
    // 针对客户端的设置
    'client' => [
        // 单位:秒
        'heart_time' => 2
    ]
];

// 三个内核:
// 第一个是转发服务器处理进程内核
// 第二个是注册服务器处理进程内核
// 第三个是业务处理服务器进程内核
// 所以需要有三套配置文件