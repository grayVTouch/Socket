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
use Event\EventCtrl\EventCtrl;
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

    // pidFile 文件
    protected $_pidFile = RUN_DIR . 'app.pid';

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

    // 记录运行时日志
    protected function _log($type , $msg){

        if (DEBUG) {
            echo $msg;
        }

        if ($type == 'run') {
            // 记录运行时日志
            $this->_logForRun->log($msg);
        } else if ($type == 'sys') {
            // 记录系统日志
            $this->_logForSys->log($msg);
        } else {
            // 待定 ...
        }
    }

    // 父进程消息回传逻辑
    protected function _returnMsgForParent($type , array $data = []){
        // 进行消息回传
        if ($data['origin_server'] == $this->server) {
            if (!isset($this->childConn[$data['origin_pid']])) {
                $line = "time: " . date('Y-m-d H:i:s' , time()) . " 消息类型：{$type}：消息回传失败，同一服务器下，对应的子进程未找到\n";
                $this->_log('run' , $line);
            }

            $to = $this->childConn[$data['origin_pid']];

            $send = [
                'type'          => 'error' ,
                'msg'           => $data['msg'] ,
                'origin_server' => $data['origin_server'] ,
                'origin_address' => $data['origin_address'] ,
                'origin_pid'    => $data['origin_pid'] ,
                'origin_message' => $data ,
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
                    $line = "time: " . date('Y-m-d H:i:s' , time()) . " 消息类型：{$type}：消息回传失败，连接不上对应的服务器\n";
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
                'msg'           => $data['msg'] ,
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

    // 子进程消息回传逻辑
    protected function _returnMsgForChild(array $data = [] , $msg){
        $send = [
            'type'          => 'error' ,
            'msg'           => $msg ,
            'origin_server' => $data['origin_server'] ,
            'origin_address' => $data['origin_address'] ,
            'origin_pid'    => $data['origin_pid'] ,
            'origin_message' => $data ,
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

        // 消息传递给父进程
        $this->pConn->send($send);
    }

    // 父进程处理子进程的消息
    protected function _msgHandleFromChild(array $data = []){
        if ($data['type'] == 'msg') {
            // 目的明确，就是与父进程直接通信，不掺杂任何其他指令
            $child = $this->childConn[$data['from_pid']];

            if (is_callable($this->_events['messageFromChild'])) {
                call_user_func($this->_events['messageFromChild'] , $child , $data['to_msg']);
            } else {
                $line = "time: " . date('Y-m-d H:i:s' , time()) . " 接收到来自子进程的消息：{$data['to_msg']}\n";
                $this->_log('run' , $line);
            }
        } else if ($data['type'] == 'error') {
            // 明确表示该数据是消息处理失败时的一个回传消息
            if ($data['origin_server'] == $this->server && $data['origin_pid'] == $this->pid) {
                if (is_callable($this->_events['error'])) {
                    call_user_func($this->_events['error'] , $data['origin_message']);
                }
            } else {
                $return_msg = $data;
                $return_msg['msg'] = "type: error 父进程不是消息源！傻逼进程弄错了消息回传的消息源！\n";

                // 错误处理
                $this->_returnMsgForParent('error' , $data);
            }
        } else {
            // 消息转发
            if ($data['to_server'] == $this->server) {
                // 同一台服务器上
                if (!isset($this->childConn[$data['to_pid']])) {
                    $return_msg = $data;
                    $return_msg['msg'] = "type: forward 父进程消息转发失败：未找到对应的子进程：pid {$data['to_pid']}\n";

                    // 转发失败消息回传
                    $this->_returnMsgForParent('forward' , $return_msg);
                    return ;
                }

                $to = $this->childConn[$data['to_pid']];

                // 要转发的数据
                $send = [
                    'type'          => 'forward' ,
                    'msg'           => '' ,
                    'origin_server' => $data['origin_server'] ,
                    'origin_address' => $data['origin_address'] ,
                    'origin_pid'    => $data['origin_pid'] ,
                    'origin_message' => $data ,
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
                        $return_msg = $data;
                        $return_msg['msg'] = "type: forward 父进程消息转发失败：无法连接到指定的服务器： server:{$data['to_server']} address: {$data['to_address']}\n";
                        // 消息回传
                        $this->_returnMsgForParent('forward' , $return_msg);
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
                    'msg'           => '' ,
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

    // 子进程处理父进程的消息
    protected function _msgHandleFromParent(array $data = []){
        if ($data['type'] == 'msg') {
            // 目的明确，就是与父进程直接通信，不掺杂任何其他指令
            $child = $this->childConn[$data['from_pid']];

            if (is_callable($this->_events['messageFromParent'])) {
                call_user_func($this->_events['messageFromParent'] , $child , $data['to_msg']);
            } else {
                $line = "time: " . date('Y-m-d H:i:s' , time()) . " 接收到来自父进程的消息：{$data['to_msg']}\n";
                $this->_log('run' , $line);
            }
        } else if ($data['type'] == 'error') {
            // 明确表示该数据是消息处理失败时的一个回传消息
            if ($data['origin_server'] == $this->server && $data['origin_pid'] == $this->pid) {
                if (is_callable($this->_events['error'])) {
                    call_user_func($this->_events['error'] , $data['origin_message']);
                }
            } else {
                $msg = "type: error 子进程消息回传失败，原因是消息发送方弄错了消息对象";
                // 错误处理
                $this->_returnMsgForChild($data , $msg);
            }
        } else {
            // 消息转发
            if ($data['to_server'] != $this->server || $data['to_pid'] != $this->pid || !isset($this->clientConn[$data['to_cid']])) {
                $msg = "type: error 子进程消息回传失败，原因可能是消息发送方弄错了对象，也可能是客户端连接不存在";
                // 转发失败消息回传
                $this->_returnMsgForChild($data , $msg);
                return ;
            }

            $to = $this->clientConn[$data['to_cid']];

            $to->send($data['to_msg']);
        }
    }

    // 父进程处理来自其他服务器的消息
    protected function _msgHandleFromServer(array $data = []){
        if ($data['type'] == 'msg') {
            // 目的明确，就是与父进程直接通信，不掺杂任何其他指令
            $child = $this->childConn[$data['from_pid']];

            if (is_callable($this->_events['messageFromServer'])) {
                call_user_func($this->_events['messageFromServer'] , $child , $data['to_msg']);
            } else {
                $line = "time: " . date('Y-m-d H:i:s' , time()) . " 接收到来自其他服务器的消息：{$data['to_msg']}\n";
                $this->_log('run' , $line);
            }
        } else if ($data['type'] == 'error') {
            // 明确表示该数据是消息处理失败时的一个回传消息
            if ($data['origin_server'] == $this->server && $data['origin_pid'] == $this->pid) {
                if (is_callable($this->_events['error'])) {
                    call_user_func($this->_events['error'] , $data['origin_message']);
                }
            } else {
                // 错误处理
                $this->_returnMsg('error' , $data);
            }
        } else {
            // 消息转发
            if ($data['to_server'] == $this->server) {
                // 同一台服务器上
                if (!isset($this->childConn[$data['to_pid']])) {
                    // 转发失败消息回传
                    $this->_returnMsg('forward' , $data);
                    return ;
                }

                $to = $this->childConn[$data['to_pid']];

                // 要转发的数据
                $send = [
                    'type'          => 'forward' ,
                    'msg'           => '' ,
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
                        $this->_returnMsg('forward' , $data);
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
                    'msg'           => '' ,
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

    // 父进程监听子进程消息
    public function monitorChild(EventCtrl $ctrl , $socket , Connection $child){
        $msg    = $child->get();

        if (empty($msg)) {
            // 销毁与子进程的连接
            unset($this->childConn[$child->id]);

            // 连接断开了
            $ctrl->destroy();

            return ;
        }

        $file = '/var/Website/Socket/parent.log';
        $f = fopen($file , 'a');
        $line = "父进程,pid = {$this->pid} 实际执行的pid = " . posix_getpid() . " 消息:{$msg}\n";
        fwrite($f , $line);

        return ;

        $data = json_decode($msg , true);

        // 处理来自子进程的消息
        // 1. 直接与父进程通信
        // 2. 父进程作为消息发送源，消息转发给子进程的某个客户端时，子进程转发失败了
        // 失败消息回传给父进程
        // 3. 父进程作为消息中转，转发消息
        $this->_msgHandleFromChild($data);
    }

    // 监听父进程消息
    public function monitorParent($ctrl , $socket , Connection $parent){
        $msg    = $parent->get();

        if (empty($msg)) {
            $ctrl->destroy();
            exit("父进程挂掉了！" . posix_getpid() . "\n");
        }

        $file = '/var/Website/Socket/child.log';
        $f = fopen($file , 'a');
        $line = "子进程,pid = {$this->pid} 实际执行的pid = " . posix_getpid() . " 消息:{$msg}\n";
        fwrite($f , $line);
        return ;

        $data = json_decode($msg , true);

        $this->_msgHandleFromParent($data);
    }

    // fork 过程中父进程要做的事情
    protected function _forkForParent($pid , $pair){
        $event = $this->event;

        // 关闭其中一个就好
        fclose($pair[0]);

        $child = $pair[1];

        // 设置阻塞模式
        stream_set_blocking($child , false);

        // 保存子进程id
        $this->_pidList[] = $pid;

        // 获取连接类名称
        $class  = $this->getClassForPro('tcp');
        $conn   = new $class($child , $pid);

        // 保存链接
        $this->childConn[$pid] = $conn;

        // 监听子进程消息
        $event::addIo($child , Event::READ , [$this , 'monitorChild'] , $conn);
    }

    // fork 过程中子进程要做的事情
    protected function _forkForChild($pair){
        $event = $this->event;

        // 清空父进程已定义事件列表
        // 防止进入父进程的代码领域
        $event::clear();

        // 设置子进程 ID
        $this->pid = posix_getpid();

        // 关闭其中一个
        fclose($pair[1]);

        // 父进程
        $parent = $pair[0];

        // 设置阻塞模式
        stream_set_blocking($parent , false);

        // 监听父进程连接
        $class      = $this->getClassForPro('tcp');
        $conn       = new $class($parent);

        // 保存父进程通信通道
        $this->pConn = $conn;

        $event::addIo($parent , Event::READ , [$this , 'monitorParent'] , $conn);

        // 创建客户端服务器
        $this->_createServer();

        // 子进程心跳检查
        $this->_heartCheckForChild();

        $event::loop();

        // 子进程结束
        exit;
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
    // 所谓的心跳检查是客户端定时向服务器发送心跳包
    protected function _heartCheckForChild(){
        $event  = $this->event;

        // 添加定时任务
        $event::addLoopTimer($this->heartTime , true , function($ctrl) use($event){
            $cur_time = time();

            // 子进程产生的客户端连接
            foreach ($this->clientConn as $k => $v)
            {
                $d = $cur_time - ($v->prevTime ?? time());

                if ($d > $this->heartTime) {
                    if (is_callable($this->_events['close'])) {
                        // 给定客户端 id
                        call_user_func($this->_events['close'] , $v->id);
                    }

                    // 停止事件循环
                    $event::$ctrls[$v->id]->destroy();

                    // 销毁断线的客户端连接
                    unset($this->clientConn[$k]);
                }

                // 定时检查客户端
                $v->ping();
            }
        });
    }

    // 子进程心跳检查
    // 所谓的心跳检查是客户端定时向服务器发送心跳包
    protected function _heartCheckForParent(){
        $event  = $this->event;

        $event::addLoopTimer($this->heartTime , true , function($ctrl) use($event){
            $cur_time = time();

            // 子进程产生的客户端连接
            foreach ($this->connWithServer as $k => $v)
            {
                $d = $cur_time - ($v->prevTime ?? time());

                if ($d > $this->heartTime) {
                    // 停止事件循环
                    $event::$ctrls[$v->id]->destroy();

                    // 销毁断线的客户端连接
                    unset($this->connWithServer[$k]);
                }

                // 定时向客户端发送心跳包
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
        $details    = $this->_proConfig['worker'];
        $address    = $this->worker;

        $conn = stream_socket_client($address , $errno , $errstr);

        if (!$conn) {
            throw new \Exception('产生 worker 链接失败');
        }

        $class  = $this->getClassForPro($details['protocol']);
        $conn   = new $class($conn);

        return $conn;
    }

    // 监听客户端链接
    public function accept($ctrl , $socket){
        // 产生客户端连接
        $client = stream_socket_accept($socket);

        // 设置阻塞模式
        stream_set_blocking($client , false);

        // 配置文件
        $child = $this->_proConfig['child'];

        $event = $this->event;

        // 获取协议
        $class = $this->getClassForPro($child['protocol']);
        $conn = new $class($client);

        // worker 进程
        $worker = $this->enableWorker ? $this->_genWorker() : null;

        // 监听客户端连接
        $cid = $event::addIo($client , Event::READ , [$this , 'monitorClient'] , $conn , $worker);

        // 设置连接 id
        $conn->id = $cid;

        // 保存客户端连接实例
        $this->clientConn[$cid] = $conn;

        if (is_callable($this->_events['open'])) {
            call_user_func($this->_events['open'] , $conn);
        }
    }

    // 监听客户端数据
    public function monitorClient($ctrl , $socket , Connection $client , $worker = null){
        $msg    = $client->get();

        if ($client instanceof WebSocketConnection) {
            if (!isset($client->isOnce)) {
                // 握手阶段，在连接中会进行处理
                // 与消息互动没有关系
                $client->isOnce = true;
                return ;
            }
        }

        if (empty($msg) || $client->isClose($msg)) {
            $cid = $client->id;

            // 这个表示客户端连接断开了
            $ctrl->destroy();

            // 删除链接
            unset($this->clientConn[$cid]);

            // 删除 worker
            unset($worker);

            // 客户端断开回调
            if (is_callable($this->_events['close'])) {
                // 传入客户端 id
                call_user_func($this->_events['close'] , $cid);
            }

            return ;
        }

        if ($client->isPing($msg)) {
            $client->pong();
            return ;
        }

        if ($client->isPong($msg)) {
            // 客户端定时向服务端发送的心跳包
            // 记录客户端维持心跳的时间，用以判断客户端是否还在
            $client->prevTime = time();
            return ;
        }

        if (is_callable($this->_events['message'])) {
            if ($this->enableWorker) {
                call_user_func($this->_events['message'] , $client , $msg);
            } else {
                // 启用了 worker 的话，你只要按照发送规定的格式发送消息给 worker 进程就好
                call_user_func($this->_events['message'] , $client , $msg , $worker);
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
            $class  = $this->getClassForPro($this->_proConfig['parent']['protocol']);
            $conn   = new $class($client);

            // 开启事件监听
            $cid = $event::addIo($client , Event::READ , function($ctrl , $socket) use($conn){
                $msg = $conn->get();

                if (empty($msg)) {
                    // 如果消息为空，表示客户端连接已经断开
                    // 停止对该资源的监听并删除该资源
                    $ctrl->destroy();

                    // 删除服务器
                    unset($this->connWithServer[$conn->id]);

                    return ;
                }

                // 如果是心跳检查,响应
                if ($conn->isPing()) {
                    $conn->pong();
                    return ;
                }

                // 如果是心跳响应,更新时间
                if ($conn->isPong()) {
                    $conn->prevTime = time();

                    return ;
                }

                $data = json_decode($msg , true);

                $this->_msgHandleFromServer($data);
            });

            $conn->id = $cid;

            $this->connWithServer[$cid] = $conn;
        });
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
                $this->_exit();
                break;
            case SIGTERM:
                $this->_exit();
                break;
            case SIGQUIT:
                $this->_exit();
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
        // 文件不存在，创建
        if (!File::checkFile($this->_pidFile)) {
            File::cFile($this->_pidFile);
        }

        // 写入格式
        $format = "%d\n";
        $lines  = sprintf($format , $this->pid);

        foreach ($this->_pidList as $v)
        {
             $lines .= sprintf($format , $v);
        }

        File::wData($this->_pidFile , $lines , 'w');
    }

    // 进程退出
    protected function _exit(){
        echo "\n\n";
        echo "主进程正在终止...等待子进程退出中...\n";
        // 父进程等待所有子进程退出
        foreach ($this->_pidList as $v)
        {
            pcntl_waitpid($v , $status , WUNTRACED);
            echo "子进程 {$v} 已经退出\n";
        }

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
        exit("所有进程已经终止，退出成功！\n");
    }

    // 显示运行 ui
    public function view(){
        echo "启动转发器成功！\n\n";
        echo "启用的事件模块:{$this->event}\n";
        echo "父进程 pid：{$this->pid}\n";
        echo "子进程数量：{$this->count}，子进程id：" . implode(' ' , $this->_pidList) . PHP_EOL;
        echo "父进程监听地址：{$this->parent}\n";
        echo "子进程监听地址：{$this->child}\n";
        echo "是否启用 Register：" . ($this->enableRegister ? "是" : "否") . "\n";
        echo "是否启用 Worker：" . ($this->enableWorker ? "是" : "否") . "\n";
        echo "正在监听....\n\n";
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

        // 对其他服务器进行心跳检查
        $this->_heartCheckForParent();

        // 安装信号
        $this->signal();

        // 保存进程的 pid 到文件
        $this->savePid();

        // 显示运行 ui
        $this->view();

        // 执行循环
        $this->loop();
    }

    // 所有进程在推出后都会执行的代码段
    function __destruct(){

    }
}