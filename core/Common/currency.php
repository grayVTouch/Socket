<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午10:02
 */

// 根据配置文件生成通讯地址
function gen_address($data){
    return "{$data['protocol']}://{$data['ip']}:{$data['port']}";
}

// 解析地址
function parse_address($address){
    if (empty($address)) {
        return [
            'protocol'  => 'unknow' ,
            'ip'        => 'unknow' ,
            'port'      => 'unknow' ,
        ];
    }

    $data   = explode('://' , $address);
    $res    = [
        'protocol' => $data[0]
    ];

    $data           = explode(':' , $data[1]);
    $res['ip']      = $data[0];
    $res['port']    = $data[1];

    return $res;
}

// 生成随机码
function gen_code(){
    return random(256 , 'mixed' , true);
}