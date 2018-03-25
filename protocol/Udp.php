<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-24
 * Time: 下午5:00
 */

namespace Protocol;


class Udp implements Protocol
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