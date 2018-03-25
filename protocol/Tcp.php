<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-24
 * Time: 下午4:57
 */

namespace Protocol;

// TCP 通信协议
class Tcp implements Protocol
{
    // 加密
    public static function encode(string $data = ''){
        return $data;
    }

    // 解密
    public static function decode(string $data = ''){
        return $data;
    }
}