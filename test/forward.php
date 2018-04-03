<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-28
 * Time: 上午10:04
 *
 */

require_once '../run.php';

use Core\Forward;

$app = new Forward();

// echo "测试用例2-不同服务器 或 相同服务器不同进程间客户端间消息通信启动成功\n";

$app->server = '192.168.150.135';
$app->enableWorker = false;
$app->enableRegister = false;
$app->count = 4;
// $app->event = '\Event\Select';
$app->parent = 'tcp://127.0.0.1:9100';
$app->child = 'websocket://127.0.0.1:9101';
// 开启 register
// $app->register = 'tcp://0.0.0.0:9102';
// 开启 worker
// $app->worker = 'tcp://0.0.0.0:9105';
$app->heartTime = 30;

// 使用 redis 作为存储数据容器
// 这边选择最简单的 string 类型数据
// 仅用作测试使用
$redis = new Redis();
$redis->connect('127.0.0.1' , 6379);
$redis->auth('364793');

$app->on('open' , function() use($app){
    // 进入到的子进程 id
    echo "接受到客户端链接，进入 pid: " . $app->pid . PHP_EOL;
});

$app->on('message' , function($conn , $msg , $worker) use($app , $redis){
    $data = json_decode($msg , true);
    $users = $redis->get('users');
    $users = json_decode($users , true);

    if ($data['type'] === 'login') {
        $users[$data['id']] = [
            'uid'       => $data['id'] ,
            'username'  => $data['username'] ,
            'server'    => $app->server ,
            'address'   => $app->parent ,
            'pid'       => $app->pid ,
            'cid'       => $conn->id
        ];

        $set = json_encode($users);

        // 更新到 redis 数据库
        $redis->set('users' , $set);

        return ;
    }

    if (!isset($users[$data['to_id']])) {
        echo "对应用户离线，要转发的数据是：{$data['msg']}\n";
        return ;
    }

    // 找到对应用户数据
    $user = $users[$data['to_id']];

    if ($user['server'] === $app->server && $user['pid'] == $app->pid) {
        // 同一个子进程
        if (!isset($app->clientConn[$user['cid']])) {
            echo "同一台服务器的同进程下！但是对应用户离线，要转发的数据是：{$data['msg']}\n";
            return;
        }

        $to = $app->clientConn[$user['cid']];

        $to->send($data['msg']);

        // 当前服务器下
    } else {
        // 不同子进程
        // 将消息转发给父进程
        // 消息发送格式请遵循系统定义
        $send = [
            'type'              => 'forward' ,
            'origin_server'     => $app->server ,
            'origin_address'    => $app->child ,
            'origin_pid'        => $app->pid ,
            'to_server'         => $user['server'] ,
            'to_address'        => $user['address'] ,
            'to_pid'            => $user['pid'] ,
            'to_cid'            => $user['cid'] ,
            'to_msg'            => $data['msg']
        ];

        $send = json_encode($send);

        // 发送给父进程后，父进程就会对消息进行中转
        $app->pConn->send($send);
    }
});

$app->on('close' , function() {
    // 关闭链接后
    echo "有客户端链接关闭了\n";
});

// 实际上转发错误
$app->on('error' , function($data) use($app){
    echo "消息转发失败，触发了消息回传机制，错误原因：{$data['msg']}\n";
});

// 运行程序
$app->run();