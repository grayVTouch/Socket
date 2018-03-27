<?php

// 程序开始
define('APP_START' , time());

// 定义系统目录
const ROOT_DIR          = __DIR__ . '/../';
const BOOTSTRAP_DIR     = ROOT_DIR . 'bootstrap/';
const CONFIG_DIR        = ROOT_DIR . 'config/';
const CONNECTION_DIR    = ROOT_DIR . 'connection/';
const EVENT_DIR         = ROOT_DIR . 'event/';
const CORE_DIR          = ROOT_DIR . 'core/';
const LOG_DIR           = ROOT_DIR . 'log/';
const PROTOCOL_DIR      = ROOT_DIR . 'protocol/';

// 设置调试模式
!defined('DEBUG') ? define('DEBUG' , true) : null;

require_once BOOTSTRAP_DIR . 'autoload.php';

// 转发进程内核
use Core\Forward;
// 协调进程内核
use Core\Register;
// 业务处理内核
use Core\Worker;

// 初始化实例
$app = new Forward();
