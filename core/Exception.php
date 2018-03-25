<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午9:36
 */

namespace Core;


class Exception
{
    // socket 创建异常处理
    public function handle(int $errno = 0 , string $errstr = ''){
        exit("错误代码: {$errno},错误描述: {$errstr}\n");
    }
}