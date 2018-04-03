<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-20
 * Time: 上午10:58
 */

namespace Event;

use Event\EventCtrl\EvCtrl;


class Ev implements Event
{
    // 必须:保存所有注册的事件
    // 否则事件不会执行
    public static $events = [];

    // 控制事件循环的对象实例集合
    public static $ctrls = [];

    // pid 集合
    public static $idList = [];

    // 生成事件行为控制者
    public static function genEventCtrl(\EvWatcher $watcher = null , string $id = ''){
        return new EvCtrl($watcher , $id);
    }

    // 添加循环定时器
    public static function addLoopTimer(int $time , bool $repeat , $callback , ...$args){
        $id = random(256 , 'mixed' , true);

        // 添加进 idList
        static::$idList[] = $id;

        static::$events[$id] = [
            'event'     => null ,
            'duration'  => 0
        ];

        // 我觉得错误就错在这个位置!!!
        // 定时循环任务
        static::$events[$id]['event'] = new \EvTimer(1 , $repeat , function($watcher) use($id , $time , $callback , &$args){
            static::$events[$id]['duration']++;

            if (static::$events[$id]['duration'] % $time === 0) {
                // 事件控制
                $ev_ctrl = static::genEventCtrl($watcher , $id);

                // 保存控制实例
                static::$ctrls[$id] = $ev_ctrl;

                $res = [$ev_ctrl];
                $res = array_merge($res , $args);

                call_user_func_array($callback , $res);
            }
        });

        return $id;
    }

    // 添加定时器
    public static function addTimer(int $after , bool $repeat , $callback , ...$args) {
        $id = random(256 , 'mixed' , true);

        // 添加进 idList
        static::$idList[] = $id;

        static::$events[$id] = new \EvTimer($after , $repeat , function($watcher) use($id , $callback , &$args){
            // 事件控制
            $ev_ctrl = static::genEventCtrl($watcher , $id);

            // 保存控制实例
            static::$ctrls[$id] = $ev_ctrl;

            // 添加到数组的首单元
            $res = [$ev_ctrl];
            $res = array_merge($res , $args);

            call_user_func_array($callback , $res);
        });

        return $id;
    }

    // 添加 socket/resource
    public static function addIo($fd , int $flag , $callback , ...$args){
        $flag   = $flag === self::READ ? \Ev::READ : ($flag === self::WRITE ? \Ev::WRITE : \Ev::READ | \Ev::WRITE);
        $id     = random(256 , 'mixed' , true);

        // 添加进 idList
        static::$idList[] = $id;

        static::$events[$id] = new \EvIo($fd , $flag , function($watcher) use($id , $fd , $callback , &$args){
            // 事件控制
            $ev_ctrl = static::genEventCtrl($watcher , $id);

            // 保存控制实例
            static::$ctrls[$id] = $ev_ctrl;

            $res = [$ev_ctrl , $fd];
            $res = array_merge($res , $args);

            call_user_func_array($callback , $res);
        });

        return $id;
    }

    // 添加信号
    public static function addSignal(int $signum , $callback , ...$args){
        $id = random(256 , 'mixed' , true);

        // 添加进 idList
        static::$idList[] = $id;

        static::$events[$id] = new \EvSignal($signum , function($watcher) use($id , $callback , &$args){
            // 事件控制
            $ev_ctrl = static::genEventCtrl($watcher , $id);

            // 保存控制实例
            static::$ctrls[$id] = $ev_ctrl;

            // 添加到数组的首单元
            array_unshift($args , $watcher->signum);
            array_unshift($args , $ev_ctrl);

            call_user_func_array($callback , $args);
        });

        return $id;
    }

    // 开始事件循环
    public static function loop(){
        \Ev::run();
    }

    // 销毁指定事件监听
    public static function destroy(string $id = ''){
        if (isset(static::$events[$id])) {
            unset(static::$events[$id]);
        }

        if (isset(static::$ctrls[$id])) {
            unset(static::$ctrls[$id]);
        }

        if (($key = array_search($id , static::$idList)) !== false) {
            unset(static::$idList[$key]);
        }

        return $id;
    }

    // 清空定义的事件
    public static function clear(){
        foreach (static::$idList as $v)
        {
            static::destroy($v);
        }
    }
}