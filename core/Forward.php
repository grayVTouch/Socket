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
    // 应用配置文件
    public $appConfig = [];
    // 心跳检查时间间隔
    public $heartTime = 30;
    // 父进程通信通道
    public $pProcess = null;

    // 是否启用 register 监控功能
    public $enableRegister = true;

    // 是否启用 worker 业务逻辑处理功能
    public $enableWorker = true;

    // 错误
    public $error = null;

    // 异常
    public $exception = null;

    // 子进程id列表
    protected $_pidList = [];

    // 子进程与链接通道的映射
    protected $_pidMap = [];

    // backlog
    protected $_backlog = 10000;

    // 受支持的协议
    public $protocolRange = ['tcp' , 'udp' , 'websocket'];

    // 启动前要做的事情
    public $before = null;

    // 启动后要做的事情
    public $after = null;

    // 子进程连接实例
    public $connectionsForChild = [];

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

    // 客户端链接实例集合
    public $connectionsForClient = [];

    // 主进程接收到的其他服务器客户端 socket 对应的 connection 实例
    public $connectionsForToOtherServer = [];

    // 主进程连接其他服务器产生的 socket 对应的 连接实例
    public $connectionsForOtherServer = [];

    // 配置文件
    protected $_sysConfig = [];

    // 与其他服务器交流格式（支持 Worker 模式）
    protected $_formatForOtherServer = [
        // 发送方机器表示码
        'from_machine'  => '' ,
        // 发送给那台服务器
        'to_machine'    => '' ,
        // 那台服务器的地址
        'to_address'    => '' ,
        // 想要通信的子进程
        'to_pid'        => '' ,
        // 想要通信的子进程下的客户端id
        'to_cid'        => '' ,
        // 要转发的消息
        'to_msg'        => ''
    ];

    // 取用的事件类型
    public $event = null;

    // 绑定的事件
    protected $_events = [
        // 主进程连接协调进程成功时回调
        'registerSuccess'   => null ,
        // 主进程连接协调进程失败时回调
        'registerFailed'    => null ,
        // 主进程创建对其他服务器链接的监听 socket 成功时回调
        'parentListenSuccess' => null ,
        // 主进程收到消息时回调(可以是其他服务器发送过来的,也可以是子进程发送的)
        'getForParent' => null ,
        // 子进程接收到消息时回调(可以是父进程发送的,也可以是客户端发送的)
        'getForChild' => null ,
        // 客户端链接成功时回调
        'open' => null ,
        // 收到客户端消息时回调
        'message' => null ,
        // 客户端断开时回调
        'close' => null
    ];

    // 日志处理
    public $log = null;

    // 设置通信环境
    public function setEnv(){
        // 当前进程ID(父进程ID,子进程会在自己的进程中设置)
        $this->pid = posix_getpid();

        // 设置进程所属服务器的标识符
        // 请在配置文件中生成
        // $this->identifier = $this->config('app.identifier');

        // 事件
        if (!isset($this->event)) {
            $this->event = $this->getEvent();
        }

        $this->appConfig['parent']      = parse_address($this->parent);
        $this->appConfig['child']       = parse_address($this->child);
        $this->appConfig['register']    = parse_address($this->register);
        $this->appConfig['worker']      = parse_address($this->worker);
    }

    // 事件名称
    public function getEvent(){
        if (extension_loaded('ev')) {
            return Ev::class;
        }

        return Select::class;
    }

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
                throw new \Exception("不支持的通信协议");
        }
    }

    // 注册事件
    public function on(string $event = '' , callable $callback){
        $this->_events[$event] = $callback;
    }

    // 产生子进程
    public function fork(){
        $event = $this->event;

        // $config = $this->config('forward.listen.child');

        // for ($i = 0; $i < $config['count']; ++$i)
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

                // 保存子进程id
                $this->_pidList[] = $pid;

                // 保存映射
                $this->_pidMap[$pid] = $child;

                // 设置阻塞模式
                stream_set_blocking($child , false);

                // 获取连接类名称
                $connection = $this->connection('tcp');
                $connection = new $connection($child);

                // 创建链接
                $this->connectionsForChild[$pid] = $connection;

                // 如果接收到子进程消息
                $event::addIo($child , Event::READ , [$this , 'getForParent'] , $connection);
            } else {
                // 设置子进程 ID
                $this->pid = posix_getpid();

                // 关闭其中一个
                fclose($pair[0]);

                // 父进程
                $parent = $pair[1];

                // 设置阻塞模式
                stream_set_blocking($parent , false);

                // 监听父进程连接
                $connection = $this->connection('tcp');
                $connection = new $connection($parent);

                // 保存父进程通信通道
                $this->pProcess = $connection;

                $event::addIo($parent , Event::READ , [$this , 'getForChild'] , $connection);

                // 做子进程该做的事情
                $this->_listenForChild();

                // 子进程心跳检查
                $this->heartCheckForChild();

                $event::loop();

                // 子进程不要进入到父进程领域
                exit;
            }
        }
    }

    // 子进程心跳检查
    public function heartCheckForChild(){
        $event  = $this->event;
        // $client = $this->config('forward.client');

        // 定时循环事件
        // $event::addLoopTimer($client['heart_time'] , true , function($ctrl){
        $event::addLoopTimer($this->heartTime , true , function($ctrl){
            // 客户端心跳检查用于保持 nginx 链接
            foreach ($this->connectionsForClient as $v)
            {
                if (!$v->closed) {
                    // 单向心跳检查
                    // 这边会产生一个问题：客户端链接已经断开，且客户端监听事件已经被删除了
                    // 但是这边的链接却还活着！！
                    $v->ping();
                } else {
                    // 销毁无效的客户端链接
                    // 防止内存泄漏
                    unset($this->connectionsForClient[$v->id]);
                }
            }
        });
    }

    /**
     * 子进程为父进程做消息转发
     * 数据交换格式:
     * [
     * 'cid' => 10 , // 客户端 id,如果没有的话,那就是发送给当前子进程的
     * 'msg' => ''  // 消息
     * ]
     */
    public function getForChild($ctrl , $socket , Connection $from){
        $msg    = $from->get();
        $data   = json_decode($msg , true);

        if (empty($msg)) {
            return ;
        }

        if (empty($data['cid'])) {
            if (is_callable($this->_events['getForChild'])) {
                call_user_func($this->_events['getForChild']->bindTo($from , null));
            } else {
                echo "接收到来自父进程的消息:{$data['msg']}\n";
            }
        } else {
            if (isset($this->connectionsForClient[$data['cid']])) {
                $to = $this->connectionsForClient[$data['cid']];

                // 发送数据
                $to->send($data['msg']);
            } else {
                $msg = "在当前客户端连接中未找到指定CID：{$data['cid']}对应的连接！这一般是由于对方下线导致的\n";

                // 如果是调试模式，输出错误信息
                if (DEBUG) {
                    echo $msg;
                }

                $this->logForRun->log($msg);
            }
        }

        if (is_callable($this->_events['getForChild'])) {
            call_user_func($this->_events['getForChild']->bindTo($from , null) , $msg);
        }
    }

    /**
     * 父进程消息转发
     * [
     *  'machine' => '' , // 服务器
     *  'address' => '' , // 通讯地址(要求完整的通讯地址,且必须是 tcp!)
     *  'pid' => '' , // 进程 id
     *  'cid' => '' , // 客户端 id
     *  'msg' => '' // 消息
     * ]
     */
    public function getForParent($ctrl , $socket , Connection $from){
        $msg    = $from->get();

        if (empty($msg)) {
            // 有的时候,就是坑爹
            // 即使子进程没有发送消息,该方法也会被触发
            // 实际获取到的是一个空消息
            return ;
        }

        $data = json_decode($msg , true);

        if ($data['machine'] === $this->identifier) {
            // 这边提供的 pid 有可能是上次通信时保留的 pid
            // 并不可信！除非用户登录，否则，并不可信！
            if (isset($this->connectionsForChild[$data['pid']])) {
                $to = $this->connectionsForChild[$data['pid']];

                // 要转发的数据
                $send = [
                    'cid'   => $data['cid'] ,
                    'msg'   => $data['msg']
                ];

                $send = json_encode($send);

                // 发送消息给指定的数据
                $to->send($send);
            } else {
                $msg = "在当前子进程连接中未找到指定PID：{$data['pid']}对应的连接！这一般是由于提供的PID是缓存的PID导致的\n";

                // 如果是调试模式，输出错误信息
                if (DEBUG) {
                    echo $msg;
                }

                $this->logForRun->log($msg);
            }
        } else {
            if (!isset($this->connectionsForToOtherServer[$data['machine']])) {
                $socket     = stream_socket_client($data['address']);
                $connection = $this->connection('tcp');
                $connection = new $connection($socket);

                $this->connectionsForToOtherServer[$data['machine']] = $connection;
            }

            $send = [
                'from_machine'  => $this->identifier ,
                'to_pid'        => $data['pid'] ,
                'to_cid'        => $data['cid'] ,
                'to_msg'        => $data['msg']
            ];

            $send = json_encode($send);

            $this->connectionsForToOtherServer[$data['machine']]->send($send);
        }

        if (is_callable($this->_events['getForParent'])) {
            call_user_func($this->_events['getForParent']->bindTo($from , null));
        }
    }

    // 子进程要做的事情
    protected function _listenForChild(){
        // 产生服务器
        $server = $this->server();

        $event = $this->event;

        // 监听客户端链接
        $event::addIo($server , Event::READ , [$this , 'accept']);
    }

    // 产生 worker 链接
    public function genWorker(){
        // $config  = $this->config("forward.server.worker");
        // $address = gen_address($config);
        $config = $this->appConfig['worker'];
        $address = $this->worker;

        $conn = stream_socket_client($address , $errno , $errstr);

        if (!$conn) {
            throw new \Exception('产生 worker 链接失败');
        }

        $connection = $this->connection($config['protocol']);
        $connection = new $connection($conn);

        return $connection;
    }

    // 监听客户端链接
    public function accept($ctrl , $server){
        // 产生客户端连接
        $client = stream_socket_accept($server);

        // 设置阻塞模式
        stream_set_blocking($client , false);

        // 客户端链接标识符
        $cid = $this->genCode();

        // 配置文件
        $child = $this->appConfig['child'];

        // 获取协议
        $connection = $this->connection($child['protocol']);
        $connection = new $connection($client , $cid);

        // worker 进程
        if ($this->enableWorker) {
            $worker = $this->genWorker();
        } else {
            $worker = null;
        }

        // 创建客户端链接实例
        $this->connectionsForClient[$cid] = $connection;

        $event = $this->event;

        $event::addIo($client , Event::READ , [$this , 'listenForClient'] , [
            'from' => $connection ,
            'worker' => $worker
        ]);

        if (is_callable($this->_events['open'])) {
            call_user_func($this->_events['open']->bindTo($connection , null));
        }
    }

    // 监听客户端数据
    public function listenForClient($ctrl , $socket , $args){
        $from   = $args['from'];
        $worker = $args['worker'];
        $msg    = $from->get();

        if (is_null($msg)) {
            return ;
        }

        if ($from->isPing($msg)) {
            $from->pong();
            return ;
        }

        if ($from->isPong($msg)) {
            return ;
        }

        if ($from->isClose($msg)) {
            $from->closed = true;

            // 关闭事件监听
            $ctrl->delete();

            // 客户端断开回调
            if (is_callable($this->_events['close'])) {
                // 传入客户端 id
                call_user_func($this->_events['close']->bindTo($from , null));
            }

            // 删除客户端链接
            unset($this->connectionsForClient[$from->id]);

            return ;
        }

        if (is_callable($this->_events['message'])) {
            if (is_null($worker)) {
                call_user_func($this->_events['message']->bindTo($from , null) , $msg);
            } else {
                // 启用了 worker 的话，你只要按照发送规定的格式发送消息给 worker 进程就好
                call_user_func($this->_events['message']->bindTo($from , null) , $msg , $worker);
            }
        } else {
            echo "接收到客户端数据:{$msg}\n";
        }
    }

    // 产生服务端
    public function server(){
        // 环境
        $context = stream_context_create([
            'socket' => [
                // 待明确的设置项
                'backlog'       => $this->_backlog ,
                // 设置端口复用
                'so_reuseport'  => true
            ]
        ]);

        // 配置文件
        // $config = $this->config('forward.listen.child');
        $config = $this->appConfig['child'];

        // print_r($config);

        if ($config['protocol'] === 'udp') {
            $address    = "{$config['protocol']}:{$config['ip']}:{$config['port']}";
            $flag       = STREAM_SERVER_BIND;
        } else {
            $address    = "tcp://{$config['ip']}:{$config['port']}";
            $flag       = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        }

        $server = stream_socket_server($address , $errno , $errstr , $flag , $context);

        if (!$server) {
            throw new \Exception("stream_socket_server 运行失败");
        }

        return $server;
    }

    // 生成随机码
    public function genCode(){
        return random(256 , 'mixed' , true);
    }

    // 链接协调进程
    public function connectRegister(){
        // 注册服务器配置文件
        // $config  = $this->config('forward.server.register');
        $address = $this->register;
        // $address = gen_address($config);

        $socket = stream_socket_client($address , $errno , $errstr);

        if (!$socket) {
            // 说明并没有设置协调服务器
            if (is_callable($this->_events['registerFailed'])) {
                call_user_func($this->_events['registerFailed'] , $errno , $errstr);
            }

            return ;
        }

        $connection = $this->connection('tcp');
        $connection = new $connection($socket);

        $event = $this->event;

        $event::addIo($socket , Event::READ , function() use($connection){
            $msg = $connection->get();

            if (empty($msg)) {
                return ;
            }

            if ($connection->isPing($msg)) {
                // 响应
                $connection->pong();
            }
        });

        // 可能需要发送监听端口的信息
        // 如果不需要,则无需理会 ....

        if (is_callable($this->_events['registerSuccess'])) {
            call_user_func($this->_events['registerSuccess'] , $connection);
        }
    }

    // 监听来自其他服务器的通信通道
    public function listenServer(){
        // $config     = $this->config('forward.listen.parent');
        // $address    = gen_address($config);
        $address = $this->parent;

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
        $server = stream_socket_server($address , $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN , $context);

        if (!$server) {
            // 如果创建失败,表示未进行分布式部署
            return ;
        }

        $event = $this->event;

        $event::addIo($server , Event::READ , function($ctrl , $socket) use($event){
            $client = stream_socket_accept($socket);

            $connection = $this->connection('tcp');
            $connection = new $connection($client);

            $event::addIo($client , Event::READ , function($ctrl , $socket) use($connection){
                $msg = $connection->get();

                if (empty($msg)) {
                    return ;
                }

                // 如果是心跳检查,返回响应
                if ($connection->isPing($msg)) {
                    $connection->pong();
                    return ;
                }

                $data = json_decode($msg , true);

                // 产生的客户端
                if (!isset($this->connectionsForOtherServer[$data['from_machine']])) {
                    // 第一次通信进保存链接
                    $this->connectionsForOtherServer[$data['from_machine']] = $connection;
                } else {
                    // 要求进行消息转发
                    if (empty($data['to_pid'])) {
                        echo "其他服务器主进程发送当前服务器主进程的消息:{$data['to_msg']}\n";
                    } else {
                        // 找到对应的子进程通信链接
                        $to = $this->connectionsForChild[$data['to_pid']];

                        $send = [
                            'cid' => $data['to_cid'] ,
                            'msg' => $data['to_msg']
                        ];

                        $send = json_encode($send);

                        // 转发消息
                        $to->send($send);
                    }
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
        $this->error = new Error($this);

        if (DEBUG) {
            set_error_handler([$this->error , 'debug']);
        } else {
            set_error_handler([$this->error , 'nodebug']);
        }
    }

    // 致命错误处理
    public function fetalErrorHandle(){
        if (DEBUG) {
            register_shutdown_function([$this->error , 'fetalDebug']);
        } else {
            register_shutdown_function([$this->error , 'fetalNoDebug']);
        }
    }

    // 设置异常处理
    public function exceptionHandle(){
        $this->exception = new Exception($this);

        if (DEBUG) {
            set_exception_handler([$this->exception , 'debug']);
        } else {
            set_exception_handler([$this->exception , 'nodebug']);
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
        $this->logForRun = new Logs($config['log_dir'] , 'run' , $config['is_send_email']);
        // 系统运行状态日志
        $this->logForSys = new Logs($config['log_dir'] , 'sys' , $config['is_send_email']);
    }

    // 保存 pid 到文件
    public function saveProcess(){
        $file = RUN_DIR . 'app.pid';

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
        $this->listenServer();

        // 安装信号
        $this->signal();

        // 保存进程的 pid 到文件
        $this->saveProcess();

        // 执行循环
        $this->loop();
    }

    // 进程退出
    public function exist(){
        // 先退出子进程
        // shell_exec('kill -s 9 ' . implode(' ' , $this->_pidList));

        // 程序结束
        define('APP_END' , time());

        $start_time = date('Y-m-d H:i:s' , APP_START);
        $end_time   = date('Y-m-d H:i:s' , APP_END);

        // 运行时间
        $duration   = APP_END - APP_START;
        $format     = format_time($duration);

        $log = "pid: {$this->pid} startTime: {$start_time} endTime: {$end_time} duration: {$duration}s format: {$format}\n";

        // 记录运行是日志
        $this->logForSys->log($log);

        exit;
    }

    // 开始执行事件循环
    public function loop(){
        $event = $this->event;

        // 开启循环监听
        $event::loop();
    }

    // 所有进程在推出后都会执行的代码段
    function __destruct(){

    }
}