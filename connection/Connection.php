<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午5:50
 */

namespace Connection;


interface Connection
{
    // 发送数据
    public function send(string $data = '');

    // 返回接收的数据
    public function get();

    // 关闭链接
    public function close();

    // 心跳检查:检查链接是否还在
    // tcp/udp,发送 ping:keepalive,要求返回 pong:keepalive
    // websocket 请参考 protocols/WebSocket.php 中的定义
    public function ping();

    // 心跳检查:响应对方的链接检查
    // tcp/udp,发送 ping:keepalive,要求返回 pong:keepalive
    // websocket 请参考 protocols/WebSocket.php 中的定义
    public function pong();

    // 检查是否是心跳检查:检查数据
    public function isPing(string $data = '');

    // 检查是否是心跳检查:响应数据
    public function isPong(string $data = '');
}