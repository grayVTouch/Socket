<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午3:57
 *
 * 转发进程内核
 */

namespace Core;

use Connection\Connection;
use Connection\TcpConnection;
use Connection\UdpConnection;
use Connection\WebSocketConnection;
use Event\Event;
use Event\Ev;
use Event\Select;
use Event\EvCtrl\EventCtrl;
use Core\Lib\File;

class Forward {
    // 主进程监听地址
    public $parent = null;

    // 子进程监听地址
    public $child = null;

    // 协调进程（注册进程）通讯地址
    public $register = null;

    // worker 进程通讯地址
    public $worker = null;

    // 子进程数量
    public $count = 1;

    // 心跳检查时间间隔
    public $heartTime = 30;

    // 是否启用 register 监控功能
    public $enableRegister = false;

    // 是否启用 worker 业务逻辑处理功能
    public $enableWorker = false;

    // 受支持的协议
    public $protocolRange = ['tcp' , 'udp' , 'websocket'];

    // 与父进程通信的连接实例
    public $pConn = null;

    // 所有子进程连接实例
    public $childConn = [];

    // 父进程与其他服务器通信的连接实例
    public $connWithServer = [];

    // 所有的客户端连接实例
    public $clientConn = [];

    // 监控连接实例
    public $registerConn = null;

    // 服务器 id
    public $server = null;

    // 当前进程 ID
    public $pid = null;

    // 取用的事件类型
    public $event = null;

    // 错误实例
    protected $_error = null;

    // 异常实例
    protected $_exception = null;

    // 子进程id列表
    protected $_pidList = [];

    // 子进程与通信连接实例
    protected $_pidMap = [];

    // backlog
    protected $_backlog = 10000;

    // 协议配置文件
    // 将 parent child register worker 解析的具体配置
    protected $_proConfig = [];

    // 应用配置配置文件
    protected $_sysConfig = [];

    // 绑定的事件
    protected $_events = [
        // 主进程连接协调进程成功时回调
        'registerSuccess'   => null ,
        // 主进程连接协调进程失败时回调
        'registerFailed'    => null ,
        // 父进程消息发送失败的时候
        'errorForParent' => null ,
        // 接收到来自其他服务器连接时
        'openFromServer' => null ,
        // 接收到来自其他服务器消息时
        'messageFromServer' => null ,
        // 其他服务器关闭关闭连接时
        'closeFromServer' => null ,
        // 子进程接收到父进程消息时
        'messageFromParent' => null ,
        // 子进程消息发送失败的时候触发
        'errorForChild' => null ,
        // 客户端链接成功时回调
        'open' => null ,
        // 收到客户端消息时回调
        'message' => null ,
        // 客户端断开时回调
        'close' => null ,
        // 发送方是客户端：消息处理失败时回调
        'error' => null
    ];

    // 运行时日志实例
    protected $_logForRun = null;

    // 系统运行日志实例
    protected $_logForSys = null;

    // 设置程序运行环境（父子进程共用）
    public function setEnv(){
        // 设置进程 id
        $this->pid = posix_getpid();

        // 获取要采用的事件模块
        if (!isset($this->event)) {
            $this->event = $this->getEvent();
        }

        // 解析通讯地址
        $this->_proConfig['parent']      = parse_address($this->parent);
        $this->_proConfig['child']       = parse_address($this->child);
        $this->_proConfig['register']    = parse_address($this->register);
        $this->_proConfig['worker']      = parse_address($this->worker);
    }

    // 事件名称
    public function getEvent(){
        if (extension_loaded('ev')) {
            return Ev::class;
        }

        return Select::class;
    }

    // 获取通信协议对应的链接类
    public function getClassForPro(string $protocol = ''){
        switch ($protocol)
        {
            case 'tcp':
                return TcpConnection::class;
            case 'udp':
                return UdpConnection::class;
            case 'websocket':
                return WebSocketConnection::class;
            default:
                throw new \Exception("不支持的通信协议");
        }
    }

    // 注册事件
    public function on(string $event = '' , callable $callback){
        $this->_events[$event] = $callback;
    }

    // fork 过程中父进程要做的事情
    protected function _forkForParent($pid , $pair){
        $event = $this->event;

        // 关闭其中一个就好
        fclose($pair[1]);

        $child = $pair[0];

        // 设置阻塞模式
        stream_set_blocking($child , false);

        // 保存子进程id
        $this->_pidList[] = $pid;

        // 获取连接类名称
        $class = $this->getClassForPro('tcp');
        $conn = new $class($child);

        // 保存链接
        $this->childConn[$pid] = $conn;

        // 监听子进程消息
        $event::addIo($child , Event::READ , [$this , 'monitorChild'] , $conn);
    }

    // 记录运行时日志
    protected function _log($type , $msg){
        $line = "父进程接收到来自子进程的消息：{$msg}\n";

        if (DEBUG) {
            echo $line;
        }

        if ($type == 'run') {
            // 记录运行时日志
            $this->_logForRun->log($line);
        } else if ($type == 'sys') {
            // 记录系统日志
            $this->_logForSys->log($line);
        } else {
            // 待定 ...
        }
    }

    // 消息回传逻辑
    protected function _returnMsg(array $data = []){
        // 进行消息回传
        if ($data['origin_server'] == $this->server) {
            if (!isset($this->childConn[$data['origin_pid']])) {
                $line = "消息类型：forward：消息回传失败，同一服务器下，对应的子进程未找到\n";
                $this->_log('run' , $line);
            }

            $to = $this->childConn[$data['to_pid']];

            $send = [
                'type'          => 'error' ,
                'origin_server' => $data['origin_server'] ,
                'origin_address' => $data['origin_address'] ,
                'origin_pid'    => $data['origin_pid'] ,
                'origin_message' => $data['origin_message'] ,
                'from_server'   => $this->server ,
                'from_address'  => $this->parent ,
                'from_pid'      => $this->pid ,
                'to_server'     => $data['to_server'] ,
                'to_address'    => $data['to_address'] ,
                'to_pid'        => $data['to_pid'] ,
                'to_cid'        => $data['to_cid'] ,
                'to_msg'        => $data['to_msg']
            ];

            $send = json_encode($send);

            $to->send($send);
        } else {
            // 不同服务器
            if (!isset($this->connWithServer[$data['origin_server']])) {
                $socket = stream_socket_client($data['origin_address'] , $errno , $errstr);

                if (!$socket) {
                    $line = "消息类型：forward：消息回传失败，连接不上对应的服务器\n";
                    $this->_log('run' , $line);
                    return ;
                }

                $details = parse_address($data['origin_address']);
                $class = $this->getClassForPro($details['protocol']);
                $conn = new $class($socket);
                $this->connWithServer[$data['origin_server']] = $conn;
            }

            $to = $this->connWithServer[$data['origin_server']];

            $send = [
                'type'          => 'error' ,
                'origin_server' => $data['origin_server'] ,
                'origin_address' => $data['origin_address'] ,
                'origin_pid'    => $data['origin_pid'] ,
                'origin_message' => $data['origin_message'] ,
                'from_server'   => $this->server ,
                'from_address'  => $this->parent ,
                'from_pid'      => $this->pid ,
                'to_server'     => $data['to_server'] ,
                'to_address'    => $data['to_address'] ,
                'to_pid'        => $data['to_pid'] ,
                'to_cid'        => $data['to_cid'] ,
                'to_msg'        => $data['to_msg']
            ];

            $send = json_encode($send);

            $to->send($send);
        }
    }

    // 父进程处理子进程的消息
    protected function _msgHandleFromChild(array $data = []){
        if ($data['type'] == 'msg') {
            // 目的明确，就是与父进程直接通信，不掺杂任何其他指令
            $child = $this->childConn[$data['from_pid']];

            if (is_callable($this->_events['messageFromChild'])) {
                call_user_func($this->_events['messageFromChild']->bindTo($child , null) , $data['to_msg']);
            } else {
                $line = "接收到来自子进程的消息：{$data['to_msg']}\n";
                $this->_log('run' , $line);
            }
        } else if ($data['type'] == 'error') {
            // 明确表示该数据是消息处理失败时的一个回传消息
            if ($data['origin_server'] == $this->server) {
                // 在同一台服务器上
                if ($data['origin_pid'] == $this->pid) {
                    if (is_callable($this->_events['error'])) {
                        call_user_func($this->_events['error'] , $data['origin_message']);
                    }
                } else {
                    if (!isset($this->childConn[$data['to_pid']])) {
                        $line = "父进程未找到待转发的子进程连接实例\n";
                        $this->_log('run' , $line);
                        return ;
                    }

                    $to = $this->childConn[$data['to_pid']];

                    // 要转发的数据
                    $send = [
                        'type'          => 'error' ,
                        'origin_server' => $data['origin_server'] ,
                        'origin_address' => $data['origin_address'] ,
                        'origin_pid'    => $data['origin_pid'] ,
                        'origin_message' => $data['origin_message'] ,
                        'from_server'   => $this->server ,
                        'from_address'  => $this->parent ,
                        'from_pid'      => $this->pid ,
                        'to_server'     => $data['to_server'] ,
                        'to_address'    => $data['to_address'] ,
                        'to_pid'        => $data['to_pid'] ,
                        'to_cid'        => $data['to_cid'] ,
                        'to_msg'        => $data['to_msg']
                    ];

                    $send = json_encode($send);

                    // 发送消息给指定的数据
                    $to->send($send);
                }
            } else {
                // 不再同一服务器上
                if (!isset($this->connWithServer[$data['origin_server']])) {
                    $socket = stream_socket_client($data['origin_address'] , $errno , $errstr);

                    if (!$socket) {
                        $line = "消息类型：error：消息处理失败，失败信息回传也失败，原因是因为连接不上对应的服务器\n";
                        $this->_log('run' , $line);
                        return ;
                    }

                    $details = parse_address($data['origin_address']);
                    $class   = $this->getClassForPro($details['protocol']);
                    $conn    = new $class($socket);
                    $this->connWithServer[$data['origin_server']] = $conn;
                }

                $to = $this->connWithServer[$data['origin_server']];

                $send = [
                    'type'          => 'error' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data['origin_message'] ,
                    'from_server'   => $this->server ,
                    'from_address'  => $this->parent ,
                    'from_pid'      => $this->pid ,
                    'to_server'     => $data['to_server'] ,
                    'to_address'    => $data['to_address'] ,
                    'to_pid'        => $data['to_pid'] ,
                    'to_cid'        => $data['to_cid'] ,
                    'to_msg'        => $data['to_msg']
                ];

                $send = json_encode($send);

                $to->send($send);
            }
        } else {
            // 消息转发
            if ($data['to_server'] == $this->server) {
                // 同一台服务器上
                if (!isset($this->childConn[$data['to_pid']])) {
                    // 消息回传
                    $this->_returnMsg($data);
                    return ;
                }

                $to = $this->childConn[$data['to_pid']];

                // 要转发的数据
                $send = [
                    'type'          => 'forward' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data['origin_message'] ,
                    'from_server'   => $this->server ,
                    'from_address'  => $this->parent ,
                    'from_pid'      => $this->pid ,
                    'to_server'     => $data['to_server'] ,
                    'to_address'    => $data['to_address'] ,
                    'to_pid'        => $data['to_pid'] ,
                    'to_cid'        => $data['to_cid'] ,
                    'to_msg'        => $data['to_msg']
                ];

                $send = json_encode($send);

                // 发送消息给指定的数据
                $to->send($send);
            } else {
                // 要转发到其他服务器
                if (!isset($this->connWithServer[$data['to_server']])) {
                    $socket     = stream_socket_client($data['to_server']);

                    if (!$socket) {
                        // 消息回传
                        $this->_returnMsg($data);
                        return ;
                    }

                    $details    = parse_address($data['to_address']);
                    $class      = $this->getClassForPro($details['protocol']);
                    $conn       = new $class($socket);

                    $this->connWithServer[$data['to_server']] = $conn;
                }

                $to = $this->connWithServer[$data['to_server']];

                $send = [
                    'type'          => 'forward' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data['origin_message'] ,
                    'from_server'   => $this->server ,
                    'from_address'  => $this->parent ,
                    'from_pid'      => $this->pid ,
                    'to_server'     => $data['to_server'] ,
                    'to_address'    => $data['to_address'] ,
                    'to_pid'        => $data['to_pid'] ,
                    'to_cid'        => $data['to_cid'] ,
                    'to_msg'        => $data['to_msg']
                ];

                $send = json_encode($send);

                $to->send($send);
            }
        }
    }

    // 资金曾处理父进程的消息
    protected function _msgHandleFromParent(array $data = []){
        if ($data['type'] == 'msg') {
            // 目的明确，就是与父进程直接通信，不掺杂任何其他指令
            $child = $this->childConn[$data['from_pid']];

            if (is_callable($this->_events['messageFromParent'])) {
                call_user_func($this->_events['messageFromParent']->bindTo($child , null) , $data['to_msg']);
            } else {
                $line = "接收到来自父进程的消息：{$data['to_msg']}\n";
                $this->_log('run' , $line);
            }
        } else if ($data['type'] == 'error') {
            // 明确表示该数据是消息处理失败时的一个回传消息
            if ($data['origin_server'] == $this->server) {
                // 在同一台服务器上
                if ($data['origin_pid'] == $this->pid) {
                    if (is_callable($this->_events['error'])) {
                        call_user_func($this->_events['error'] , $data['origin_message']);
                    }
                } else {
                    if (!isset($this->childConn[$data['to_pid']])) {
                        $line = "子进程未找到待转发的子进程连接实例\n";
                        $this->_log('run' , $line);
                        return ;
                    }

                    $to = $this->childConn[$data['to_pid']];

                    // 要转发的数据
                    $send = [
                        'type'          => 'error' ,
                        'origin_server' => $data['origin_server'] ,
                        'origin_address' => $data['origin_address'] ,
                        'origin_pid'    => $data['origin_pid'] ,
                        'origin_message' => $data['origin_message'] ,
                        'from_server'   => $this->server ,
                        'from_address'  => $this->child ,
                        'from_pid'      => $this->pid ,
                        'to_server'     => $data['to_server'] ,
                        'to_address'    => $data['to_address'] ,
                        'to_pid'        => $data['to_pid'] ,
                        'to_cid'        => $data['to_cid'] ,
                        'to_msg'        => $data['to_msg']
                    ];

                    $send = json_encode($send);

                    // 发送消息给指定的数据
                    $to->send($send);
                }
            } else {
                // 不再同一服务器上
                if (!isset($this->connWithServer[$data['origin_server']])) {
                    $socket = stream_socket_client($data['origin_address'] , $errno , $errstr);

                    if (!$socket) {
                        $line = "消息类型：error：消息处理失败，失败信息回传也失败，原因是因为连接不上对应的服务器\n";
                        $this->_log('run' , $line);
                        return ;
                    }

                    $details = parse_address($data['origin_address']);
                    $class   = $this->getClassForPro($details['protocol']);
                    $conn    = new $class($socket);
                    $this->connWithServer[$data['origin_server']] = $conn;
                }

                $to = $this->connWithServer[$data['origin_server']];

                $send = [
                    'type'          => 'error' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data['origin_message'] ,
                    'from_server'   => $this->server ,
                    'from_address'  => $this->child ,
                    'from_pid'      => $this->pid ,
                    'to_server'     => $data['to_server'] ,
                    'to_address'    => $data['to_address'] ,
                    'to_pid'        => $data['to_pid'] ,
                    'to_cid'        => $data['to_cid'] ,
                    'to_msg'        => $data['to_msg']
                ];

                $send = json_encode($send);

                $to->send($send);
            }
        } else {
            // 消息转发
            if ($data['to_server'] == $this->server) {
                // 同一台服务器上
                if (!isset($this->childConn[$data['to_pid']])) {
                    // 消息回传
                    $this->_returnMsg($data);
                    return ;
                }

                $to = $this->childConn[$data['to_pid']];

                // 要转发的数据
                $send = [
                    'type'          => 'forward' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data['origin_message'] ,
                    'from_server'   => $this->server ,
                    'from_address'  => $this->child ,
                    'from_pid'      => $this->pid ,
                    'to_server'     => $data['to_server'] ,
                    'to_address'    => $data['to_address'] ,
                    'to_pid'        => $data['to_pid'] ,
                    'to_cid'        => $data['to_cid'] ,
                    'to_msg'        => $data['to_msg']
                ];

                $send = json_encode($send);

                // 发送消息给指定的数据
                $to->send($send);
            } else {
                // 要转发到其他服务器
                if (!isset($this->connWithServer[$data['to_server']])) {
                    $socket     = stream_socket_client($data['to_server']);

                    if (!$socket) {
                        // 消息回传
                        $this->_returnMsg($data);
                        return ;
                    }

                    $details    = parse_address($data['to_address']);
                    $class      = $this->getClassForPro($details['protocol']);
                    $conn       = new $class($socket);

                    $this->connWithServer[$data['to_server']] = $conn;
                }

                $to = $this->connWithServer[$data['to_server']];

                $send = [
                    'type'          => 'forward' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data['origin_message'] ,
                    'from_server'   => $this->server ,
                    'from_address'  => $this->child ,
                    'from_pid'      => $this->pid ,
                    'to_server'     => $data['to_server'] ,
                    'to_address'    => $data['to_address'] ,
                    'to_pid'        => $data['to_pid'] ,
                    'to_cid'        => $data['to_cid'] ,
                    'to_msg'        => $data['to_msg']
                ];

                $send = json_encode($send);

                $to->send($send);
            }
        }
    }

    // 父进程监听子进程消息
    public function monitorChild(EventCtrl $ctrl , Connection $child){
        $msg    = $child->get();

        if (empty($msg)) {
            // 有的时候,就是坑爹
            // 即使子进程没有发送消息,该方法也会被触发
            // 实际获取到的是一个空消息
            return ;
        }

        $data = json_decode($msg , true);

        // 处理来自子进程的消息
        $this->_msgHandleFromChild($data);
    }

    // fork 过程中子进程要做的事情
    protected function _forkForChild($pair){
        $event = $this->event;

        // 设置子进程 ID
        $this->pid = posix_getpid();

        // 关闭其中一个
        fclose($pair[0]);

        // 父进程
        $parent = $pair[1];

        // 设置阻塞模式
        stream_set_blocking($parent , false);

        // 监听父进程连接
        $class      = $this->getClassForPro('tcp');
        $conn = new $class($parent);

        // 保存父进程通信通道
        $this->pConn = $conn;

        $event::addIo($parent , Event::READ , [$this , 'monitorParent'] , $conn);

        // 创建客户端服务器
        $this->_createServer();

        // 子进程心跳检查
        $this->_clienHeartCheck();

        $event::loop();

        // 子进程结束
        exit;
    }

    // 监听父进程消息
    public function monitorParent($ctrl , Connection $parent){
        $msg    = $parent->get();

        if (empty($msg)) {
            // 如果消息为空，则表示程序发生了做死的情况！
            // 无需理会即可
            return ;
        }

        $data = json_decode($msg , true);

        $this->_msgHandleFromParent($data);
    }

    // 产生子进程
    public function fork(){
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
                // 父进程要做的事情
                $this->_forkForParent($pid , $pair);
            } else {
                // 子进程要做的事情
                $this->_forkForChild($pair);
            }
        }
    }

    // 子进程心跳检查
    protected function _clienHeartCheck(){
        $event  = $this->event;

        $event::addLoopTimer($this->heartTime , true , function($ctrl){
            // 客户端心跳检查用于保持 nginx 连接
            foreach ($this->clientConn as $v)
            {
                $v->ping();
            }
        });
    }

    // 子进程要做的事情
    protected function _createServer(){
        // 环境
        $context = stream_context_create([
            'socket' => [
                // 待明确的设置项
                'backlog'       => $this->_backlog ,
                // 设置端口复用
                'so_reuseport'  => true
            ]
        ]);

        $details = $this->_proConfig['child'];

        // print_r($config);

        if ($details['protocol'] === 'udp') {
            $address    = "udp://{$details['ip']}:{$details['port']}";
            $flag       = STREAM_SERVER_BIND;
        } else {
            $address    = "tcp://{$details['ip']}:{$details['port']}";
            $flag       = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        }

        $server = stream_socket_server($address , $errno , $errstr , $flag , $context);

        if (!$server) {
            throw new \Exception("stream_socket_server 运行失败");
        }

        $event = $this->event;

        // 监听客户端链接
        $event::addIo($server , Event::READ , [$this , 'accept']);
    }

    // 产生 worker 链接
    protected function _genWorker(){
        // $config  = $this->config("forward.server.worker");
        // $address = gen_address($config);
        $config = $this->_proConfig['worker'];
        $address = $this->worker;

        $conn = stream_socket_client($address , $errno , $errstr);

        if (!$conn) {
            throw new \Exception('产生 worker 链接失败');
        }

        $class = $this->getClassForPro($config['protocol']);
        $conn = new $class($conn);

        return $conn;
    }

    // 监听客户端链接
    public function accept($ctrl , $server){
        // 产生客户端连接
        $client = stream_socket_accept($server);

        // 设置阻塞模式
        stream_set_blocking($client , false);

        // 客户端链接标识符
        $cid = gen_code();

        // 配置文件
        $child = $this->_proConfig['child'];

        // 获取协议
        $class = $this->getClassForPro($child['protocol']);
        $conn = new $class($client , $cid);

        // worker 进程
        $worker = $this->enableWorker ? $this->_genWorker() : null;

        // 创建客户端链接实例
        $this->clientConn[$cid] = $conn;

        $event = $this->event;

        $event::addIo($client , Event::READ , [$this , 'monitorClient'] , $conn , $worker);

        if (is_callable($this->_events['open'])) {
            call_user_func($this->_events['open']->bindTo($conn , null));
        }
    }

    // 监听客户端数据
    public function monitorClient($ctrl , $socket , Connection $client , $worker){
        $msg    = $client->get();

        if (empty($msg)) {
            // 程序做死的情况下经常发生这种事情
            return ;
        }

        if ($client->isPing($msg)) {
            $client->pong();
            return ;
        }

        if ($client->isPong($msg)) {
            return ;
        }

        if ($client->isClose($msg)) {
            // 删除事件
            $ctrl->delete();

            // 删除链接
            unset($this->clientConn[$client->id]);

            // 删除 worker
            unset($worker);

            // 客户端断开回调
            if (is_callable($this->_events['close'])) {
                // 传入客户端 id
                call_user_func($this->_events['close']);
            }

            return ;
        }

        if (is_callable($this->_events['message'])) {
            if ($this->enableWorker) {
                call_user_func($this->_events['message']->bindTo($client , null) , $msg);
            } else {
                // 启用了 worker 的话，你只要按照发送规定的格式发送消息给 worker 进程就好
                call_user_func($this->_events['message']->bindTo($client , null) , $msg , $worker);
            }
        } else {
            echo "子进程接收到客户端数据:{$msg}\n";
        }
    }

    // 连接协调进程（保留监控功能）
    public function connectRegister(){
        $socket = stream_socket_client($this->register , $errno , $errstr);

        if (!$socket) {
            if (is_callable($this->_events['registerFailed'])) {
                call_user_func($this->_events['registerFailed'] , $errno , $errstr);
            }

            return ;
        }

        $details    = parse_address($this->register);
        $class      = $this->getClassForPro($details['protocol']);
        $conn       = new $class($socket);

        // 保存与监控进程的连接
        $this->registerConn = $conn;

        // 报告当前进程标识符，通讯地址
        // 报告子进程数量
        // 报告每个子进程链接数量

        if (is_callable($this->_events['registerSuccess'])) {
            call_user_func($this->_events['registerSuccess'] , $conn);
        }
    }

    // 监听来自其他服务器的通信通道
    public function monitorServer(){
        $context = stream_context_create([
            'socket' => [
                'backlog'       => $this->_backlog ,
                // 主进程监听其他服务器进程,不允许端口复用
                // 因为如果进行端口复用后,无法确保消息进入到当前进程
                // 也就无法进行消息转发了
                'so_reuseport'  => false
            ]
        ]);

        // 通信服务器
        $server = stream_socket_server($this->parent , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN , $context);

        if (!$server) {
            // 如果创建失败,表示未进行分布式部署
            return ;
        }

        $event = $this->event;

        $event::addIo($server , Event::READ , function($ctrl , $socket) use($event){
            $client = stream_socket_accept($socket);
            $class  = $this->getClassForPro('tcp');
            $conn   = new $class($client);

            $event::addIo($client , Event::READ , function($ctrl , $socket) use($conn){
                $msg = $conn->get();

                if (empty($msg)) {
                    // 如果消息为空，表示程序做死
                    return ;
                }

                $data = json_decode($msg , true);

                if ($data['type'] == 'msg') {
                    if (!isset($this->connWithServer[$data['from_server']])) {
                        $this->connWithServer[$data['from_server']] = $conn;
                    }

                    // 通信失败
                    $origin = $data['to_msg']['origin'];

                    if (!isset($this->childConn[$origin['to_pid']])) {
                        $line = "接收到其他服务器的反馈消息，但是由于原发送方已经不存在了，此次通信结束\n";

                        if (DEBUG) {
                            echo $line;
                        }

                        $this->_logForRun->log($line);

                        return ;
                    }

                    // 消息转发
                    $to = $this->childConn[$origin['to_pid']];

                    $send = [
                        'type'  => 'msg' ,
                        'cid'   => $data['to_cid'] ,
                        'msg'   => $data['to_msg']
                    ];

                    $send = json_encode($send);

                    $to->send($send);
                } else {
                    // 消息转发
                    if ($data['to_server'] != $this->server) {
                        $line = "接收到来自其他服务器的消息，但是这个消息本不该发送到这儿的\n";

                        if (DEBUG) {
                            echo $line;
                        }

                        // 记录运行日志
                        $this->logForRun->log($line);

                        $send = [
                            'type'          => 'msg' ,
                            'from_server'   => $this->server ,
                            'to_server'     => $data['from_server'] ,
                            'to_address'    => null ,
                            'to_pid'        => null ,
                            'to_cid'        => null ,
                            'to_msg'        => [
                                'status'    => 'failed' ,
                                'msg'       => $line ,
                                'origin'    => $data
                            ]
                        ];

                        $send = json_encode($send);

                        // 通知发送方
                        $conn->send($send);

                        return ;
                    }
                    
                    // 如果未找到对应的进程 id
                    if (!isset($this->childConn[$data['to_pid']])) {
                        $line = "当前服务器上未找到对应的进程id\n";

                        if (DEBUG) {
                            echo $line;
                        }

                        // 记录运行日志
                        $this->logForRun->log($line);

                        $send = [
                            'type'          => 'msg' ,
                            'from_server'   => $this->server ,
                            'to_server'     => $data['from_server'] ,
                            'to_address'    => null ,
                            'to_pid'        => null ,
                            'to_cid'        => null ,
                            'to_msg'        => [
                                'status'    => 'failed' ,
                                'msg'       => $line ,
                                'origin'    => $data
                            ]
                        ];

                        $send = json_encode($send);

                        // 通知发送方
                        $conn->send($send);

                        return ;
                    }

                    $to = $this->childConn[$data['to_pid']];
                    $send = [
                        'cid'   => $data['to_cid'] ,
                        'msg'   => $data['to_msg']
                    ];

                    // 如果未找到对应的客户端连接
                    if (!isset($this->clientConn[$data['to_cid']])) {
                        $send['type'] = 'msg';
                    } else {
                        $send['type'] = 'forward';
                    }

                    $send = json_encode($send);

                    // 转发消息
                    $to->send($send);
                }
            });
        });
    }

    // 加载配置文件
    public function loadEnvironment(){
        $sys_config = File::getFileList(CONFIG_DIR , 'file');

        // 系统配置文件
        foreach ($sys_config as $v)
        {
            $filename   = get_filename($v);
            $extension  = get_extension($v);
            $filename   = str_replace('.' . $extension , '' , $filename);

            $this->_sysConfig[$filename] = require_once $v;
        }
    }

    // 获取系统配置
    public function _getConfig($dir , array &$data = [] , $key , $args = []){
        if (empty($key)) {
            throw new \Exception('未提供待查找的 key');
        }

        $keys   = explode('.' , $key);
        $len    = count($keys);
        $index  = 0;
        $res    = null;

        $do = function($v , &$config , $dir) use(&$do , &$res , $key , $keys , $len ,  &$index , $args){
            $index++;

            $file = format_path($dir . $v);

            if (File::checkDir($file)) {
                if (!isset($config[$v])) {
                    $config[$v] = null;
                }

                $file .= '/';
            } else {
                $tmp_file = $file . '.php';

                if ($len - 2 < $index && File::checkFile($tmp_file) && !isset($config[$v])) {
                    $config[$v] = require_once $tmp_file;
                }
            }

            if ($index === $len) {
                if (!isset($config[$v])) {
                    throw new \Exception("未找到 {$key} 对应键值");
                }

                if (is_array($config[$v])) {
                    return $res = $config[$v];
                }

                return $res = vsprintf($config[$v] , $args);
            } else {
                $do($keys[$index] , $config[$v] , $file);
            }
        };

        $do($keys[$index] , $data , $dir);

        return $res;
    }

    // 获取配置文件
    public function config($key = null , array $args = []){
        return $this->_getConfig(CONFIG_DIR  , $this->_sysConfig , $key , $args);
    }

    // 错误处理
    public function errorHandle(){
        $this->_error = new Error($this);

        if (DEBUG) {
            set_error_handler([$this->_error , 'debug']);
        } else {
            set_error_handler([$this->_error , 'nodebug']);
        }
    }

    // 致命错误处理
    public function fetalErrorHandle(){
        if (DEBUG) {
            register_shutdown_function([$this->_error , 'fetalDebug']);
        } else {
            register_shutdown_function([$this->_error , 'fetalNoDebug']);
        }
    }

    // 设置异常处理
    public function exceptionHandle(){
        $this->_exception = new Exception($this);

        if (DEBUG) {
            set_exception_handler([$this->_exception , 'debug']);
        } else {
            set_exception_handler([$this->_exception , 'nodebug']);
        }
    }

    // 安装信号
    public function signal(){
        $event = $this->event;

        $event::addSignal(SIGINT , [$this , 'signalHandle']);
        $event::addSignal(SIGQUIT , [$this , 'signalHandle']);
        $event::addSignal(SIGTERM , [$this , 'signalHandle']);
    }

    // 信号处理器
    public function signalHandle($ctrl , $signal){
        switch ($signal)
        {
            case SIGINT:
                $this->exist();
                break;
            case SIGTERM:
                $this->exist();
                break;
            case SIGQUIT:
                $this->exist();
                break;
            default:
                throw new \Exception("不支持的信号");
        }
    }

    // 日志处理
    public function logHandle(){
        $config = $this->config('log');

        // 运行时日志
        $this->_logForRun = new Logs($config['log_dir'] , 'run' , $config['is_send_email']);
        // 系统运行状态日志
        $this->_logForSys = new Logs($config['log_dir'] , 'sys' , $config['is_send_email']);
    }

    // 保存 pid 到文件
    public function savePid(){
        $file = $this->config('log.log_dir') . 'app.pid';

        // 文件不存在，创建
        if (!File::checkFile($file)) {
            File::cFile($file);
        }

        // 清空之前写入的数据
        File::wData($file , '' , 'w');

        // 写入格式
        $format = "%d\n";
        $lines  = sprintf($format , $this->pid);

        foreach ($this->_pidList as $v)
        {
             $lines .= sprintf($format , $v);
        }

        File::wData($file , $lines , 'a');
    }

    // 进程退出
    public function exist(){
        // 程序结束
        define('APP_END' , time());

        $start_time = date('Y-m-d H:i:s' , APP_START);
        $end_time   = date('Y-m-d H:i:s' , APP_END);

        // 运行时间
        $duration   = APP_END - APP_START;
        $format     = format_time($duration);

        $log = "startTime: {$start_time} endTime: {$end_time} duration: {$duration}s format: {$format}\n";

        // 记录运行是日志
        $this->_logForSys->log($log);

        // 主进程退出
        exit;
    }

    // 开始执行事件循环
    public function loop(){
        $event = $this->event;

        // 开启循环监听
        $event::loop();
    }

    // 开始跑程序
    public function run(){
        // 设置错误处理
        $this->errorHandle();

        // 设置异常处理
        $this->exceptionHandle();

        // 致命错误处理
        $this->fetalErrorHandle();

        // 设置日志记录类
        $this->logHandle();

        // 加载配置文件
        $this->loadEnvironment();

        // 设置运行环境
        $this->setEnv();

        // 产生子进程
        $this->fork();

        if ($this->enableRegister) {
            // 产生与协调进程间的通信通道(可选)
            $this->connectRegister();
        }

        // 监听来自其他服务器的通信通道(可选)
        $this->monitorServer();

        // 安装信号
        $this->signal();

        // 保存进程的 pid 到文件
        $this->savePid();

        // 执行循环
        $this->loop();
    }

    // 所有进程在推出后都会执行的代码段
    function __destruct(){

    }
}