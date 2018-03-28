<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-27
 * Time: 下午4:34
 */

require_once __DIR__ . '/../../run.php';

use Core\Worker;

$app = new Worker();
$app->identifier = '192.168.150.135';
$app->enableRegister = true;
$app->register = 'tcp://127.0.0.1:9102';
// worker 进程必须使用 tcp 连接
$app->parent = 'tcp://127.0.0.1:9104';
$app->child = 'tcp://127.0.0.1:9105';
$app->count = 4;
$app->event = '\Event\Select';

$app->on('message' , function(){
    var_dump("worker 进程接受到客户端消息");
});

// 运行程序
$app->run();