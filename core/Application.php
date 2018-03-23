<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午3:57
 */

namespace Core;

use \Event\Ev;
use \Event\Select;
use \Event\Event;

class Application {
    // 远程地址
    protected $_remote = '';

    // 连接协议
    protected $_protocol = '';

    // ip
    protected $_ip = '';

    // port
    protected $_port = '';

    // 连接地址
    protected $_address = '';

    // 子进程ID
    protected $_pidList = [];

    // backlog
    protected $_backlog = 10000;

    // 受支持的协议
    protected $_protocolRange = ['tcp' , 'udp' , 'websocket'];

    // 设置进程数量
    public $count = 1;

    function __construct(string $remote = ''){
        $data = $this->parse($remote);

        if (!in_array($data['protocol'] , $this->_protocolRange)) {
            throw new \Exception("不支持的协议类型，当前受支持的协议类型有：" . implode('，' , $this->_protocolRange));
        }

        $this->_remote      = $remote;
        $this->_protocol    = $data['protocol'];
        $this->_ip          = $data['ip'];
        $this->_port        = $data['port'];
        $this->_address     = $data['address'];
    }

    // 事件名称
    public function event(){
        if (extension_loaded('ev')) {
            return Ev::class;
        }

        return Select::class;
    }

    // 协议解析
    public function parse($remote){
        $data = explode(':' , $remote);

        if (count($data) !== 3) {
            return false;
        }

        return [
            'protocol'  => $data[0] ,
            'address'   => str_replace($data[0] . ':' , $remote) ,
            'ip'        => $data[1] ,
            'port'      => $data[2] ,
        ];
    }

    // 产生子进程
    public function fork(){
        for ($i = 0; $i < $this->count; ++$i)
        {
            $pair = stream_socket_pair(STREAM_PF_INET , STREAM_SOCK_STREAM , STREAM_IPPROTO_IP);

            $pid = pcntl_fork();

            if ($pid < 0) {
                throw new \Exception("产生子进程失败，请联系程序开发人员");
            } else if ($pid > 0) {
                // 保存子进程 id
                $this->_pidList[$pid] = $pair[0];

                // 关闭其中一个就好
                fclose($pair[1]);
            } else {
                fclose($pair[0]);

                // 父进程
                $parent = $pair[1];

                // 做子进程该做的事情
                $this->listen($parent);

                exit;
            }
        }
    }

    // 子进程要做的事情
    protected function listen($parent){
        // 产生服务器
        $server = $this->server();

        // 事件
        $event = $this->event();

        // 添加事件jfkfklfjkl
        $event::addIo($server , Event::READ , );

    }

    // 设置端口复用
    public function context(){
        return [
            'socket' => [
                'backlog'       => $this->_backlog ,
                'so_reuseport'  => true
            ]
        ];
    }

    // 产生服务端
    public function server(){
        $context = stream_context_create($this->context());

        if (in_array($this->_protocol , ['tcp' , 'udp'])) {
            $address = "{$this->_protocol}:{$this->_address}";
            $flag    = $this->_protocol === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

            return stream_socket_server($address , $errno , $errstr , $flag , $context);
        }
    }

    // 开始跑程序
    public function run(){

    }
}