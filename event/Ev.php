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
    protected $_events = [];

    public function addTimer($after , $repeat , callable $callback) {
        $this->_events[] = new EvTimer($after , $repeat , $callback);
    }

    public function addIo($fd , $flag , callable $callback){
        $flag = $flag === self::READ ? Ev::READ : ($flag === self::WRITE ? Ev::WRITE : Ev::READ | Ev::WRITE);
        $this->_events[] = new EvIo($fd , $flag , $callback);
    }

    public function addSignal($signum , callable $callback){
        $this->_events[] = new EvSignal($signum , $callback);
    }

    public function loop(){
        Ev::run();
    }

    public function delete($key){

    }
}