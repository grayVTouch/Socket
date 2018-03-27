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
        'protocol' => 'tcp' ,
        'ip'       => '0.0.0.0' ,
        'port'     => 9100 ,
        // 进程数量
        'count'    => 1
    ]
];