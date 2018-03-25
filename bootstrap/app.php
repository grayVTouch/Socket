<?php

// 程序开始
define('APP_START' , microtime(true));

// 定义系统目录
const ROOT_DIR          = __DIR__ . '/../';
const BOOTSTRAP_DIR     = ROOT_DIR . 'bootstrap/';
const CONNECTION_DIR    = ROOT_DIR . 'connection/';
const EVENT_DIR         = ROOT_DIR . 'event/';
const CORE_DIR          = ROOT_DIR . 'core/';
const FUNCTION_DIR      = ROOT_DIR . 'function/';
const LOG_DIR           = ROOT_DIR . 'log/';
const PROTOCOL_DIR      = ROOT_DIR . 'protocol/';

// 设置调试模式
!defined('DEBUG') ? define('DEBUG' , true) : null;

require_once BOOTSTRAP_DIR . 'autoload.php';

use Core\Application;

$app = new Application();

// 程序结束
define('APP_END' , microtime(true));
