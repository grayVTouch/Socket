<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-27
 * Time: 下午4:34
 */

require_once 'run.php';

$app->on('serverOpen' , function(){
    var_dump("有新的服务器上线了！");
});

$app->on('serverMessage' , function(){
    var_dump("收到服务器消息！");
});

$app->on('open' , function(){
    var_dump("新的客户端上线");
});


$app->on('message' , function(){
    var_dump("接受到客户端消息");
});

$app->on('close' , function(){
    var_dump("有客户端链接关闭");
});

// 运行程序
$app->run();