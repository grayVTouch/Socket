<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-20
 * Time: 下午4:21
 */

namespace Event;


class Select implements Event
{
    // 事件标识符列表
    public static $events = [];

    // 定时器事件列表
    public static $timerFunctions = [];

    // io 事件列表
    public static $ioFunctions = [];

    // 信号事件列表(这个居然是多余的 ...)
    public static $signalFunctions = [];

    // 轮训间隔时间,单位 us
    public static $interval = 1; //20000; // 1 * 1000 * 1000; // 20000;

    // 开始时间
    public static $sTime = 0;

    // 当前时间
    public static $curTime = 0;

    // 结束时间
    public static $eTime = 0;

    // 资源列表
    public static $resource = [];

    // 添加定时器
    public static function addTimer(int $after , bool $repeat , $callback , $args = null){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = true;

        static::$timerFunctions[$id] = [
            'after'     => $after ,
            'repeat'    => $repeat ,
            'callback'  => $callback ,
            'args'      => $args
        ];
    }

    // 添加 io 事件
    public static function addIo($fd , int $flag , $callback , $args = null , array $except = [] , $wait_s = 0 , $wait_ns = 0){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = true;

        static::$ioFunctions[$id] = [
            'fd'        => $fd ,
            'flag'      => $flag ,
            'callback'  => $callback ,
            'args'      => $args ,
            'except'    => $except ,
            'wait_s'    => $wait_s ,
            'wait_ns'   => $wait_ns
        ];
    }

    // 添加信号事件
    public static function addSignal(int $signal , $callback , $args = null){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = true;

        static::$signalFunctions[$id] = [
            'signal'    => $signal ,
            'callback'  => $callback ,
            'args'      => $args
        ];

        // 安装信号处理
        pcntl_signal($signal , function() use($callback , $args){
            call_user_func($callback , $args);
        });
    }

    // 开始循环
    public static function loop(){
        static::$sTime = time();

        while (true)
        {
            // 推出循环
            if (empty(static::$events)) {
                break;
            }

            // 定时器监听
            static::_loopForTimer();
            // io 监听
            static::_loopForIo();
            // 信号 监听
            static::_loopForSignal();

            // 轮训间隔
            usleep(static::$interval);
        }
    }

    // 定时器 轮询
    protected static function _loopForTimer(){
        // 当前时间
        static::$curTime = time();

        // 时间间隔
        $interval = static::$curTime - static::$sTime;

        // 上一次执行回调函数时间点
        $prev_time = isset(static::$prevTimeForTimer) ? static::$prevTimeForTimer : static::$sTime;

        $duration = static::$curTime - $prev_time;

        // 距离上一次调用未 >= 1s,不允许触发
        if ($duration < 1) {
            return ;
        }

        foreach (static::$timerFunctions as &$v)
        {
            // 1. 未达到指定时间
            // 2. 设置了非重复触发事件,事件已经触发过一次的
            if ($interval < $v['after'] || isset($v['is_trigger']) && $v['is_trigger']) {
                continue ;
            }

            // 触发定时器事件
            call_user_func($v['callback'] , $v['args']);

            if (!$v['repeat'] && !isset($v['is_trigger'])) {
                // 设置触发标志
                $v['is_trigger'] = true;
            }

            // 上一次调用时间
            if (!isset(static::$prevTimeForTimer) || isset(static::$prevTimeForTimer) && static::$prevTimeForTimer === $prev_time) {
                static::$prevTimeForTimer = time();
            }
        }
    }

    // io 轮询
    protected static function _loopForIo(){
        foreach (static::$ioFunctions as &$v)
        {
            $read   = [$v['fd']];
            $write  = [$v['fd']];
            $except = $v['except'];

            stream_select($read , $write , $except , $v['wait_s'] , $v['wait_ns']);

            if ($v['flag'] === self::READ || $v['flag'] === self::BOTH) {
                foreach ($read as $v1)
                {
                    call_user_func($v['callback'] , $v1 , $v['args']);
                }
            }

            if ($v['flag'] === self::WRITE || $v['flag'] === self::BOTH) {
                foreach ($write as $v1)
                {
                    call_user_func($v['callback'] , $v1 , $v['args']);
                }
            }
        }
    }

    // 信号 轮询
    protected static function _loopForSignal(){
        // 如果有排队中的信号,立即触发
        pcntl_signal_dispatch();
    }

    // 删除时间
    // @param $id ID
    public static function delete(string $id){
        // 从已定义的事件列表中删除指定事件，如果有的话
        if (isset(static::$events[$id])) {
            unset(static::$events[$id]);
        }

        // 从已定义的时间回调函数中删除指定函数，如果有的话
        if (isset(static::$timerFunctions[$id])) {
            unset(static::$ioFunctions[$id]);
        }

        // 从已定义的io回调函数中删除指定函数，如果有的话
        if (isset(static::$ioFunctions[$id])) {
            unset(static::$ioFunctions[$id]);
        }

        // 从已定义的信号回调函数中删除指定函数，如果有的话
        if (isset(static::$signalFunctions[$id])) {
            unset(static::$ioFunctions[$id]);
        }

        // 返回被删除的事件 ID
        return $id;
    }
}