<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午5:49
 */

namespace Connection;

use Protocol\WebSocket;

class WebSocketConnection implements Connection
{
    protected $_connection = null;

    // 判断是否握手
    protected $_shakeHand = false;

    function __construct($connection) {
        if (!is_resource($connection)) {
            throw new \Exception("不是 socket 客户端链接");
        }

        $this->_connection = $connection;
    }

    // 发送数据
    public function send(string $data = ''){
        if (!$this->_shakeHand) {
            $encode = WebSocket::hand($data);
            $this->_shakeHand = true;
        } else {
            $encode = WebSocket::encode($data);
        }

        fwrite($this->_connection , $encode);
    }

    // 接收数据
    public function get(){
        $encode = fread($this->_connection , 65535);

        if (!$this->_shakeHand) {
            $this->send($encode);
            return ;
        }

        $decode = WebSocket::decode($encode);

        return $decode;
    }

    // 关闭链接
    public function close(){
        $close = WebSocket::close();
        fwrite($this->_connection , $close);
        fclose($this->_connection);
    }

    // ping
    public function ping(){
        $ping = WebSocket::ping();
        fwrite($this->_connection , $ping);
    }

    // pong
    public function pong(){
        $pong = WebSocket::pong();

        fwrite($this->_connection , $pong);
    }

    // 判断是否是 ping
    public function isPing(string $data = ''){
        return WebSocket::isPing($data);
    }

    public function isPong(string $data = ''){
        return WebSocket::isPong($data);
    }
}