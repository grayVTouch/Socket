<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午3:57
 */

namespace Core;

use Connection\Connection;
use Connection\TcpConnection;
use Connection\UdpConnection;
use Connection\WebSocketConnection;
use Event\Event;
use Event\Ev;
use Event\Select;

class Application {
    // 远程地址
    public $remote = '';

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

    // 启动前要做的事情
    public $before = null;

    // 启动后要做的事情
    public $after = null;

    // socket 服务端创建成功时要做的事情
    public $createServerSuccess = null;

    // socket 服务端创建失败时要做的事情
    public $createServerFailed = null;

    // socket 服务端接收到 client 连接时要做的事情
    public $clientConnected = null;

    // 接收到 client 连接发送的消息时要做的事情
    public $onmessage = null;

    // 发送给 client 消息前要做的事情
    public $beforeSendToClient = null;

    // 发送给 cient 消息后要做的事情
    public $afterSendToClient = null;

    // client 连接断开后要做的事情
    public $clientClose = null;

    // server 连接断开后要做的事情
    public $serverClose = null;

    // 设置进程数量
    public $count = 1;

    // 连接通道ss
    protected $_connectionsForChild = [];

    // 机器识别id
    public $identifier = null;

    // 主进程ID
    public $pid = null;

    // 父子进程通信格式
    // 来自谁
    // 机器标识码
    // 通信地址
    // 进程ID
    // 客户端ID
    protected $_format = [
        //
    ];

    // 端口重用
    public $reuseport = false;

    // 客户端链接 socket 集合
    protected $_clients = [];

    // 客户端链接实例集合
    protected $_connectionsForClient = [];

    function __construct(string $remote = ''){
        $this->remote = $remote;
    }

    // 设置通信环境
    public function setEnv(){
        $data = $this->parse($this->remote);

        if (!in_array($data['protocol'] , $this->_protocolRange)) {
            throw new \Exception("不支持的协议类型，当前受支持的协议类型有：" . implode('，' , $this->_protocolRange));
        }

        // 通信相关参数
        $this->_protocol    = $data['protocol'];
        $this->_ip          = $data['ip'];
        $this->_port        = $data['port'];
        $this->_address     = $data['address'];

        // 与其他服务器沟通的监听端口
        $this->_port1 = (int) $this->_port + 1;

        // 进程相关参数
        $this->pid = posix_getpid();

        // 设置进程所属服务器的标识符
        $this->identifier = $this->genCode();
    }

    // 事件名称
    public function event(){
        if (extension_loaded('ev')) {
            return Ev::class;
        }

        return Select::class;
    }

    // 主进程 pid,
    public function listenOtherServer(){
        /*
        $remote = "tcp://{$this->_ip}:{$this->_port1}";
        $server = $this->server();
        $event  = $this->event();

        $event::addIo($server , Event::READ , [$this , '']);
        */
    }

    // 主进程监听其他服务器链接


    // 获取对应的客户端连接
    public function connection(string $protocol = ''){
        switch ($protocol)
        {
            case 'tcp':
                return TcpConnection::class;
            case 'udp':
                return UdpConnection::class;
            case 'websocket':
                return WebSocketConnection::class;
            default:
                Exception::handle(0 , '不支持的通信协议!');
        }
    }

    // 协议解析
    public function parse($remote){
        $data = explode(':' , $remote);

        if (count($data) < 2) {
            return false;
        }

        if (count($data) === 2) {
            $data[2] = 80;
        }

        return [
            'protocol'  => $data[0] ,
            'address'   => "{$data[1]}:{$data[2]}" ,
            'ip'        => $data[1] ,
            'port'      => $data[2] ,
        ];
    }

    // 产生子进程
    public function fork(){
        $event = $this->event();

        for ($i = 0; $i < $this->count; ++$i)
        {
            // 创建成对的 unix套接字 用于父子进程间通信
            // STREAM_PF_UNIX 表示使用 unix 套接字
            // STREAM_SOCK_STREAM 表示使用 tcp 全双工通信
            // STREAM_IPPROTO_IP,表示协议.
            $pair = stream_socket_pair(STREAM_PF_UNIX , STREAM_SOCK_STREAM , STREAM_IPPROTO_IP);

            // 产生子进程
            $pid = pcntl_fork();

            if ($pid < 0) {
                throw new \Exception("产生子进程失败，请联系程序开发人员");
            } else if ($pid > 0) {
                // 关闭其中一个就好
                fclose($pair[1]);

                $child = $pair[0];

                // 保存子进程 id
                $this->_pidList[$pid] = $child;

                // 设置阻塞模式
                stream_set_blocking($child , false);

                // 获取连接类名称
                $connection = $this->connection('tcp');
                $connection = new $connection($child);

                // 创建链接
                $this->_connectionsForChild[$pid] = $connection;

                // 如果接收到子进程消息
                $event::addIo($child , Event::READ , [$this , 'forwardForParent'] , $connection);

                // 开启循环监听
                $event::loop();
            } else {
                // 关闭其中一个
                fclose($pair[0]);

                // 父进程
                $parent = $pair[1];

                // 设置阻塞模式
                stream_set_blocking($parent , false);

                // 监听父进程连接
                $connection = $this->connection('tcp');
                $connection = new $connection($parent);

                $event::addIo($parent , Event::READ , [$this , 'forwardForChild'] , $connection);

                // 做子进程该做的事情
                $this->_listen($parent);

                $event::loop();

                // 子进程不要进入到父进程领域
                exit;
            }
        }
    }

    // 子进程为父进程做消息转发
    public function forwardForChild($socket , Connection $from){
        $msg    = $from->get();
        $data   = json_decode($msg , true);

        if (empty($data['cid'])) {
            if (is_callable($this->onmessage)) {
                call_user_func($this->onmessage->bindTo($from , null));
            } else {
                echo "接收到来自父进程的消息:{$data['msg']}\n";
            }
        } else {
            $to = $this->_connectionsForClient[$data['cid']];

            // 发送数据
            $to->send($data['msg']);
        }
    }

    // 父进程消息转发
    public function forwardForParent($socket , Connection $from){
        $msg    = $from->get();
        $data   = json_decode($msg , true);

        $to = $this->_connectionsForChild[$data['pid']];

        // 要转发的数据
        $send = [
            'cid'   => $data['cid'] ,
            'msg'   => $data['msg']
        ];

        $send = json_encode($send);

        // 发送消息给指定的数据
        $to->send($send);
    }

    // 子进程要做的事情
    protected function _listen($parent){
        // 子进程中端口复用
        $this->reuseport = true;

        // 产生服务器
        $server = $this->server();

        // 事件
        $event = $this->event();

        // 监听客户端链接
        $event::addIo($server , Event::READ , [$this , 'accept']);

    }

    // 监听客户端链接
    public function accept($server){
        if (is_callable($this->createServerSuccess)) {
            call_user_func($this->createServerSuccess);
        }

        // 产生客户端连接
        $client = stream_socket_accept($server);

        // 设置阻塞模式
        stream_set_blocking($client , false);

        // 客户端链接标识符
        $cid    = $this->genCode();

        // 保存客户端链接
        $this->_clients[$cid] = $client;

        // 获取协议
        $connection = $this->connection($this->_protocol);
        $connection = new $connection($client);

        // 创建客户端链接实例
        $this->_connectionsForClient[$cid] = $connection;

        $event = $this->event();

        $event::addIo($client , Event::READ , [$this , 'listenForClient'] , $connection);
    }

    // 监听客户端数据
    public function listenForClient($socket , Connection $connection){
        $msg = $connection->get();

        if (is_callable($this->onmessage)) {
            call_user_func($this->onmessage->bindTo($connection , null) , $msg);
        } else {
            echo "接收到客户端数据:{$msg}\n";
        }
    }

    // 设置 socket 环境
    public function context(){
        return [
            'socket' => [
                // 待明确的设置项
                'backlog'       => $this->_backlog ,
                // 设置端口复用
                'so_reuseport'  => $this->reuseport
            ]
        ];
    }

    // 产生服务端
    public function server(){
        $context = stream_context_create($this->context());

        if ($this->_protocol === 'udp') {
            $address    = "{$this->_protocol}:{$this->_address}";
            $flag       = STREAM_SERVER_BIND;
        } else {
            $address    = "tcp://{$this->_address}";
            $flag       = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        }

        $server = stream_socket_server($address , $errno , $errstr , $flag , $context);

        if (!$server) {
            if (is_callable($this->createServerFailed)) {
                call_user_func($this->createServerFailed , $errno , $errstr);
            } else {
                Exception::handle();
            }
        }

        return $server;
    }

    // 生成随机码
    public function genCode(){
        return random(256 , 'mixed' , true);
    }

    // 开始跑程序
    public function run(){
        // 设置运行环境
        $this->setEnv();

        // 产生与协调进程间的通信通道
        // 监听来自其他服务器的通信通道

        // 产生子进程
        $this->fork();
    }
}