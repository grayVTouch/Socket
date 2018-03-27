<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午11:13
 */

require_once 'run.php';

// 接受到消息的时候
$app->on('message' , function($data){
    var_dump($data);

    $this->send("服务器响应");
});

$app->run();