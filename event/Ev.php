<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-20
 * Time: 上午10:58
 */

namespace Event;

use Event\EvCtrl\EvCtrl;


class Ev implements Event
{
    // 必须:保存所有注册的事件
    // 否则事件不会执行
    public static $events = [];

    // 生成事件行为控制者
    public static function genEvCtrl(\EvWatcher $watcher = null , string $id = ''){
        return new EvCtrl($watcher , $id);
    }

    public static function addLoopTimer(int $time , bool $repeat , $callback , ...$args){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = [
            'event'     => null ,
            'duration'  => 0
        ];

        static::$events[$id]['event'] = new \EvTimer(1 , $repeat , function($watcher) use($id , $time , $callback , &$args){
            static::$events[$id]['duration']++;

            // 事件控制
            $ev_ctrl = static::genEvCtrl($watcher , $id);

            // 添加到数组的首单元
            array_shift($args , $ev_ctrl);

            if (static::$events[$id]['duration'] % $time === 0) {
                call_user_func_array($callback , $args);
            }
        });
    }

    public static function addTimer(int $after , bool $repeat , $callback , ...$args) {
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvTimer($after , $repeat , function($watcher) use($id , $callback , &$args){
            // 事件控制
            $ev_ctrl = static::genEvCtrl($watcher , $id);

            // 添加到数组的首单元
            array_shift($args , $ev_ctrl);

            call_user_func_array($callback , $args);
        });
    }

    public static function addIo($fd , int $flag , $callback , ...$args){
        $flag   = $flag === self::READ ? \Ev::READ : ($flag === self::WRITE ? \Ev::WRITE : \Ev::READ | \Ev::WRITE);
        $id     = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvIo($fd , $flag , function($watcher) use($id , $fd , $callback , &$args){
            // 事件控制
            $ev_ctrl = static::genEvCtrl($watcher , $id);

            // 添加到数组的首单元
            array_shift($args , $fd);
            array_shift($args , $ev_ctrl);

            call_user_func_array($callback , $args);
        });
    }

    public static function addSignal(int $signum , $callback , ...$args){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvSignal($signum , function($watcher) use($id , $callback , &$args){
            // 事件控制
            $ev_ctrl = static::genEvCtrl($watcher , $id);

            // 添加到数组的首单元
            array_shift($args , $watcher->signum);
            array_shift($args , $ev_ctrl);

            call_user_func_array($callback , $args);
        });
    }

    public static function loop(){
        \Ev::run();
    }

    public static function delete(string $id = ''){
        if (isset(static::$events[$id])) {
            unset(static::$events[$id]);
        }

        return $id;
    }
}