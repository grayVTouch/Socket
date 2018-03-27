<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-24
 * Time: 下午5:17
 */

namespace Connection;

trait TraitConnection {
    protected $_connection = null;

    protected $_ping = 'ping:keepalive';

    protected $_pong = 'pong:keepalive';

    // 表示链接是否还在使用
    public $closed = false;

    // 客户端链接id
    public $id = null;

    function __construct($connection , $id = ''){
        if (!is_resource($connection)) {
            throw new \Exception("不是 socket 客户端链接");
        }

        $this->_connection = $connection;
        $this->id = $id;
    }

    // 写入数据
    public function send(string $data = ''){
        fwrite($this->_connection , $data);
    }

    // 获取数据
    public function get(){
        return fread($this->_connection , 65535);
    }

    // 关闭链接
    public function close(){
        fclose($this->_connection);
    }

    // ping
    public function ping(){
        $this->send($this->_ping);
    }

    // pong
    public function pong(){
        $this->send($this->_pong);
    }

    // 是否是心跳检查
    public function isPing(string $data = ''){
        return $data === $this->_ping;
    }

    // 是否是心跳检查:相应
    public function isPong(string $data = ''){
        return $data === $this->_pong;
    }
}