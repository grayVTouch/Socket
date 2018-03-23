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

    public static function addTimer(int $after , bool $repeat , callable $callback) {
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvTimer($after , $repeat , $callback);
    }

    public static function addIo($fd , int $flag , callable $callback){
        $flag   = $flag === self::READ ? Ev::READ : ($flag === self::WRITE ? Ev::WRITE : Ev::READ | Ev::WRITE);
        $id     = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvIo($fd , $flag , $callback);
    }

    public static function addSignal(int $signum , callable $callback){
        $id = random(256 , 'mixed' , true);

        static::$events[$id] = new \EvSignal($signum , $callback);
    }

    public static function loop(){
        Ev::run();
    }

    public static function delete(string $id){
        if (isset(static::$events[$id])) {
            unset(static::$events[$id]);
        }

        return $id;
    }
}