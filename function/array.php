<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-22
 * Time: 下午5:31
 */

/*
 * 按要求返回随机数
 * @param  Integer    $len        随机码长度
 * @param  String     $type       随机码类型  letter | number | mixed
 * @return Array
 */
function random(int $len = 4 , string $type = 'mixed' , bool $is_return_string = false){
    $type_range = array('letter','number','mixed');

    if (!in_array($type , $type_range)){
        throw new Exception('参数 2 类型错误');
    }

    if (!is_int($len) || $len < 1) {
        $len = 1;
    }

    $result = [];
    $letter = array('a' , 'b' , 'c' , 'd' , 'e' , 'f' , 'g' , 'h' , 'i' , 'j' , 'k' , 'l' , 'm' , 'n' , 'o' , 'p' , 'q' , 'r' , 's' , 't' , 'u' , 'v' , 'w' , 'x' , 'y' , 'z');

    for ($i = 0; $i < count($letter) - $i; ++$i)
    {
        $letter[] = strtoupper($letter[$i]);
    }

    if ($type === 'letter'){
        for ($i = 0; $i < $len; ++$i)
        {
            $rand = mt_rand(0 , count($letter) - 1);

            shuffle($letter);

            $result[] = $letter[$rand];
        }
    }

    if ($type === 'number') {
        for ($i = 0; $i < $len; ++$i)
        {
            $result[] = mt_rand(0 , 9);
        }
    }

    if ($type === 'mixed'){
        for ($i = 0; $i < $len; ++$i)
        {
            $mixed = [];
            $rand  = mt_rand(0 , count($letter) - 1);

            shuffle($letter);

            $mixed[] = $letter[$rand];
            $mixed[] = mt_rand(0,9);

            $rand = mt_rand(0 , count($mixed) - 1);

            shuffle($mixed);

            $result[] = $mixed[$rand];
        }
    }

    return $is_return_string ? implode('' , $result) : $result;
}