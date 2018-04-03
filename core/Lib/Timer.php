<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-31
 * Time: 下午7:12
 *
 * 定时任务
 */

namespace core\Lib;


class Timer
{
    // 任务列表
    public static $tasks = [];

    // 初始化定时器
    public static function init(){
        pcntl_signal(SIGALRM , [__CLASS__ , 'signalHandle']);
    }

    // 添加任务
    public static function add($interval = 1 , bool $repeat = false , callable $callback = null , ...$args){
        static::$tasks[] = [
            'interval'  => $interval ,
            'repeat'    => $repeat ,
            'callback'  => $callback ,
            'args'      => $args
        ];
    }

    // 信号处理
    public static function signalHandle($signal){
        if ($signal !== SIGALRM) {
            return ;
        }

        // 闹钟信号
        pcntl_alarm(1);
    }

    //

}