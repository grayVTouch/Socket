<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午11:13
 */

function test(){
    $test = function(){
        static $i = 0;
        $i++;
        return $i;
    };

    return $test();
}

$one = test();
$two = test();

var_dump($one , $two);

function test1(){
    static $i = 0;
    $i++;

    return $i;
}

$one = test1();
$two = test1();

var_dump($one , $two);