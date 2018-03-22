<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-20
 * Time: 下午4:25
 */

namespace Event;

interface Event
{
    // io 读取标志
    public const READ = 1;

    // io 写入标志
    public const WRITE = 2;

    // io 监听读写标志
    public const BOTH = 3;

    // 添加定时器事件
    public function addTimer($after , $repeat , callable $callback);

    // 添加 io 事件
    public function addIo($fd , $flag , callable $callback);

    // 添加 信号事件
    public function addSignal($signum , callable $callback);

    // 开始监听
    public function loop();

    // 删除事件标识符
    // 为了避免新进一个链接,产生一个 链接标识符
    // 链接断开后,该连接标识符仍然还在
    // 持续这样的话,会导致内存使用量持续增加
    public function delete($key);
}