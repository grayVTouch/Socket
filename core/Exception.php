<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午9:36
 */

namespace Core;


class Exception extends Logs
{
    function __construct($app){
        // 构造函数
        parent::__construct($app->config('log.log_dir') , 'exception' , $app->config('log.is_send_email'));
    }

    // 非调试模式（记录日志）
    public function nodebug($excep){
        $msg = $this->genExcepStr($excep);

        $this->log($msg);
    }

    // 调试模式（直接输出）
    public function debug($excep){
        $log = $this->genExcepStr($excep);

        $this->log($log);

        exit($log);
    }
}