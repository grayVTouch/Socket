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
    protected $_events = [];

    // 定时器事件列表
    protected $_timerFunctions = [];

    // io 事件列表
    protected $_ioFunctions = [];

    // 信号事件列表(这个居然是多余的 ...)
    protected $_signalFunctions = [];

    // 轮训间隔时间,单位 us
    protected $_interval = 1; //20000; // 1 * 1000 * 1000; // 20000;

    // 开始时间
    protected $_sTime = 0;

    // 当前时间
    protected $_curTime = 0;

    // 结束时间
    protected $_eTime = 0;

    // 资源列表
    protected $_resource = [];

    // 添加定时器
    public function addTimer($after , $repeat , callable $callback){
        $this->_timerFunctions[] = [
            'after'     => $after ,
            'repeat'    => $repeat ,
            'callback'  => $callback
        ];
    }

    // 添加 io 事件
    public function addIo($fd , $flag , callable $callback , array $except = [] , $wait_s = 0 , $wait_ns = 0){
        $this->_ioFunctions[] = [
            'fd'        => $fd ,
            'flag'      => $flag ,
            'callback'  => $callback ,
            'except'    => $except ,
            'wait_s'    => $wait_s ,
            'wait_ns'   => $wait_ns
        ];
    }

    // 添加信号事件
    public function addSignal($signal , callable $callback){
        pcntl_signal($signal , $callback);
    }

    // 开始循环
    public function loop(){
        $this->_sTime = time();

        while (true)
        {
            // 定时器监听
            $this->_loopForTimer();
            // io 监听
            $this->_loopForIo();
            // 信号 监听
            $this->_loopForSignal();

            // 轮训间隔
            usleep($this->_interval);
        }
    }

    // 定时器 轮询
    protected function _loopForTimer(){
        // 当前时间
        $this->_curTime = time();

        // 时间间隔
        $interval = $this->_curTime - $this->_sTime;

        // 上一次执行回调函数时间点
        $prev_time = isset($this->_prevTimeForTimer) ? $this->_prevTimeForTimer : $this->_sTime;

        $duration = $this->_curTime - $prev_time;

        // 距离上一次调用未 >= 1s,不允许触发
        if ($duration < 1) {
            return ;
        }

        foreach ($this->_timerFunctions as &$v)
        {
            // 1. 未达到指定时间
            // 2. 设置了非重复触发事件,事件已经触发过一次的
            if ($interval < $v['after'] || isset($v['is_trigger']) && $v['is_trigger']) {
                continue ;
            }

            // 触发定时器事件
            call_user_func($v['callback']);

            if (!$v['repeat'] && !isset($v['is_trigger'])) {
                // 设置触发标志
                $v['is_trigger'] = true;
            }

            // 上一次调用时间
            if (!isset($this->_prevTimeForTimer) || isset($this->_prevTimeForTimer) && $this->_prevTimeForTimer === $prev_time) {
                $this->_prevTimeForTimer = time();
            }
        }
    }

    // io 轮询
    protected function _loopForIo(){
        foreach ($this->_ioFunctions as &$v)
        {
            $read   = [$v['fd']];
            $write  = [$v['fd']];
            $except = $v['except'];

            stream_select($read , $write , $except , $v['wait_s'] , $v['wait_ns']);

            if ($v['flag'] === self::READ || $v['flag'] === self::BOTH) {
                foreach ($read as $v1)
                {
                    call_user_func($v['callback'] , $v1);
                }
            }

            if ($v['flag'] === self::WRITE || $v['flag'] === self::BOTH) {
                foreach ($write as $v1)
                {
                    call_user_func($v['callback'] , $v1);
                }
            }
        }
    }

    // 信号 轮询
    protected function _loopForSignal(){
        // 如果有排队中的信号,立即触发
        pcntl_signal_dispatch();
    }

    // 删除
    public function delete($delete){

    }
}