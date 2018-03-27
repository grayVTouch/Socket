<?php

/**
 * *****************************
 * author 陈学龙 grayVTouch
 * 纯 php 构建的分布式通信框架
 * *****************************
 */

// 进程类型，必须事先指定
// 可选的进程类型： register forward worker
// register：协调进程，用于监控服务器数量
// forward：转发进程，用于进行消息转发
// worker，业务逻辑处理进程，用于处理业务逻辑
define('PROCESS_TYPE' , 'register');


// 启动
require_once __DIR__ . '/bootstrap/app.php';