<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-20
 * Time: 上午10:58
 */

namespace Event;


class Ev implements Event
{
    // 必须:保存所有注册的事件
    // 否则事件不会执行
    public static $events = [];

    public static function addTimer(int $after , bool $repeat , $callback , $args = null) {
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvTimer($after , $repeat , function() use($callback , $args){
            call_user_func($callback , $args);
        });
    }

    public static function addIo($fd , int $flag , $callback , $args = null){
        $flag   = $flag === self::READ ? \Ev::READ : ($flag === self::WRITE ? \Ev::WRITE : \Ev::READ | \Ev::WRITE);
        $id     = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvIo($fd , $flag , function() use($fd , $callback , $args){
            call_user_func($callback , $fd , $args);
        });
    }

    public static function addSignal(int $signum , $callback , $args = null){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvSignal($signum , function() use($callback , $args){
            call_user_func($callback , $args);
        });
    }

    public static function loop(){
        \Ev::run();
    }

    public static function delete(string $id){
        if (isset(static::$events[$id])) {
            unset(static::$events[$id]);
        }

        return $id;
    }
}