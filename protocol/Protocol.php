<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-24
 * Time: 下午4:45
 */

namespace Protocol;


interface Protocol
{
    // 加密
    public static function encode(string $data = '');

    // 解密
    public static function decode(string $data = '');
}