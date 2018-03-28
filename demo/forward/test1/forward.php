<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-28
 * Time: 上午10:04
 *
 */

require_once __DIR__ . '/../../../run.php';

use Core\Forward;

$app = new Forward();

echo "测试用例1-单进程客户端间消息通信启动成功\n";

// * 用于测试转发内核：
// * 1. 当前测试仅适用于客户端链接在同一个进程下时,客户端消息间进行消息转发
// * 2. worker 功能关闭
// * 3. Register 功能关闭
// 机器识别码（建议使用公网 ip 作为标识符）
$app->identifier = '192.168.150.135';
$app->enableRegister = false;
$app->enableWorker = false;
$app->count = 1;
$app->parent = 'tcp://127.0.0.1:9100';
$app->child = 'websocket://127.0.0.1:9101';
// 以下可选安装
// $app->register = 'tcp://0.0.0.0:9102';
// $app->worker = 'tcp://0.0.0.0:9103';
$app->heartTime = 30;

$users = [];

$app->on('open' , function() use($app){
    // 进入到的子进程 id
    var_dump("接受到客户端链接，进入 pid: " . $app->pid);
});

$app->on('message' , function($msg) use($app){
    $data = json_decode($msg , true);

    if ($data['type'] === 'login') {
        if (!isset($GLOBALS['users'][$data['id']])) {
            // 用于 id
            $GLOBALS['users'][$data['id']] = [
                'id'        => $data['id'] ,
                'username'  => $data['username'] ,
                'cid'       => $this->id
            ];

            return ;
        } else {
            // 用于 id
            $GLOBALS['users'][$data['id']]['cid'] = $this->id;
        }

        return ;
    }

    if (!isset($GLOBALS['users'][$data['to_id']])) {
        echo "对应用户离线，要转发的数据是：{$data['msg']}\n";
        return ;
    }

    // 找到对应用户数据
    $user = $GLOBALS['users'][$data['to_id']];

    // 找到对应的客户端链接
    if (!isset($app->connectionsForClient[$user['cid']])) {
        echo "对应用户离线，要转发的数据是：{$data['msg']}\n";
        return ;
    }

    $conn = $app->connectionsForClient[$user['cid']];

    // 发送给指定用户
    $conn->send($data['msg']);
});

$app->on('close' , function() {
    // 关闭链接后
    var_dump("有客户端链接关闭了");
});

// 运行程序
$app->run();