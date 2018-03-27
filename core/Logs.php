<?php

/**
 * ******************
 * autor grayVTouch 2017-11-28
 * 日志类
 * ******************
 */

namespace Core;

use Core\Lib\File;

class Logs {
	protected $_logDir  = '';
	protected $_logFile = '';
	// 发生错误的时候是否发送通知邮件
	protected $_isSendMail = false;

	public function getFilename($type){
	    $type_range = [
	        'debug'     => 'debug' , // 调试日志
	        'error'     => 'error' ,   // 错误日志
	        'exception' => 'exception' ,  // 异常日志
	        'run'       => 'runtime'     // 运行日志
        ];

	    if (in_array($type , array_keys($type_range))) {
            return $type_range[$type];
        }

        // 如果不在范围内的话，返回用户自定义的错误类型
        return $type;
    }

	function __construct($log_dir = '' , $log_type = '' , $is_send_mail = false){
		if (!File::checkDir($log_dir)) {
			throw new \Exception('日志目录不存在： ' . $log_dir);
		}

		// 日志目录
		$this->_logDir = format_path($log_dir) . '/';

        // 是否发送邮件
        $this->_isSendMail = is_bool($is_send_mail) ? $is_send_mail : false;

        // 日志类型
        $filename = $this->getFilename($log_type);

		// 日志名称
		$filename        .= '.log';
		$this->_logFile  = $this->_logDir . $filename;

        // 日志文件不存在则创建
		if (!File::checkFile($this->_logFile)) {
			File::cFile($this->_logFile);
		}
	}


    // 记录日志文件
    public function log($msg = ''){
        File::wData($this->_logFile , $msg);

        // 如果要发送日志文件
        if ($this->_isSendMail) {

        }
    }

	// 生成异常字符串（写入日志用）
	public function genExcepStr($excp){
		$trace = $excp->getTrace();
		$file  = $excp->getFile();
		$line  = $excp->getLine();
		$message   = $excp->getMessage();
		$i	   = 0;

		$msg  = '----- Exception Start -----';
		$msg .= "\r\n";
		$msg .= "Exception: Time:" . date('Y-m-d H:i:s' , time()) . "  File:$file  Line:$line  Message:$message";
		$msg .= "\r\n";

		foreach ($trace as $v)
			{
				$file = isset($v['file']) ? $v['file'] : 'unknow';
				$line = isset($v['line']) ? $v['line'] : 'unknow';
				$msg .= '#' . $i . ' ' . $file . ' ';

				if (isset($v['class'])) {
					$msg .=  $v['class'] . $v['type'] . $v['function'] . '(' . $line . ')';
				} else {
					$msg .= $v['function'] . '(' . $line . ')';
				}

				$msg .= "\r\n";
				$i++;
			}

		$msg .= "----- Exception End ------";
		$msg .= "\r\n";
		$msg .= "\r\n";

		return $msg;
	}

	// 生成错误字符串（写入日志用）
	public function genErrStr($trace , $file , $line , $message){
		$i	  = 0;
		$msg  = '----- Error Start -----';
		$msg .= "\r\n";
		$msg .= "Error: Time:" . date('Y-m-d H:i:s' , time()) . "  File:$file  Line:$line  Message:$message";
		$msg .= "\r\n";

		foreach ($trace as $v)
			{
				$file = isset($v['file']) ? $v['file'] : 'unknow';
				$line = isset($v['line']) ? $v['line'] : 'unknow';

				$msg .= '#' . $i . ' ' . $file . ' ';

				if (isset($v['class'])) {
					$msg .= $v['class'] . $v['type'] . $v['function'] . '(' . $line . ')';
				} else {
					$msg .= $v['function'] . '(' . $line . ')';
				}

				$msg .= "\r\n";
				$i++;
			}

		$msg .= "----- Error End ------";
		$msg .= "\r\n";
		$msg .= "\r\n";

		return $msg;
	}

	// 生成致命错误字符串
    public function genFetalErrStr($file , $line , $message){
        $i	  = 0;
        $msg  = '----- FetalError Start -----';
        $msg .= "\r\n";
        $msg .= "Error: Time:" . date('Y-m-d H:i:s' , time()) . "  File:$file  Line:$line  Message:$message";
        $msg .= "\r\n";
        $msg .= "----- FetalError End ------";
        $msg .= "\r\n";
        $msg .= "\r\n";

        return $msg;
    }

}