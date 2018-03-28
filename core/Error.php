<?php
namespace Core;

class Error extends Logs {

	private static $_errLevelInfoList = [
         '1'    => 'E_ERROR：致命的运行错误。错误无法恢复，终止执行脚本' ,
         '2'    => 'E_WARNING：运行时警告（非致命错误）。脚本继续运行' ,
         '4'    => 'E_PARSE：编译时解析错误。解析错误只由分析器产生' ,
         '8'    => 'E_NOTICE：运行时提醒(这些经常是你代码中的bug引起的，也可能是有意的行为造成的。)' ,
         '16'   => 'E_CORE_ERROR' ,
         '32'   => 'E_CORE_WARNING PHP启动时初始化过程中的警告(非致命性错)。' ,
         '64'   => 'E_COMPILE_ERROR 编译时致命性错。这就像由Zend脚本引擎生成了一个E_ERROR。' ,
         '128'  => 'E_COMPILE_WARNING 编译时警告(非致命性错)。这就像由Zend脚本引擎生成了一个E_WARNING警告。' ,
         '256'  => 'E_USER_ERROR 用户自定义的错误消息。这就像由使用PHP函数trigger_error（程序员设置E_ERROR）' ,
         '512'  => 'E_USER_WARNING 用户自定义的警告消息。这就像由使用PHP函数trigger_error（程序员设定的一个E_WARNING警告）' ,
         '1024' => 'E_USER_NOTICE 用户自定义的提醒消息。这就像一个由使用PHP函数trigger_error（程序员一个E_NOTICE集）' ,
         '2048' => 'E_STRICT 编码标准化警告。允许PHP建议如何修改代码以确保最佳的互操作性向前兼容性。' ,
         '4096' => 'E_RECOVERABLE_ERROR 开捕致命错误。这就像一个E_ERROR，但可以通过用户定义的处理捕获（又见set_error_handler（））' ,
         '8191' => 'E_ALL'
    ];

	function __construct($app){
        // 构造函数
        parent::__construct($app->config('log.log_dir') , 'error' , $app->config('log.is_send_email'));
    }

	// 获取错误等级的 中文说明
	public function getLevel($err_level = 0){
		switch ($err_level) 
        {
            case E_ERROR:
                $err_level = 'E_ERROR';
                break;
            case E_WARNING:
                $err_level = 'E_WARNING';
                break;
            case E_NOTICE:
                $err_level = 'E_NOTICE';
                break;
            case E_USER_ERROR:
                $err_level = 'E_USER_ERROR';
                break;
            case E_USER_WARNING:
                $err_level = 'E_USER_WARNING';
                break;
            case E_USER_NOTICE:
                $err_level = 'E_USER_NOTICE';
                break;
            case E_CORE_ERROR:
                $err_level = 'E_CORE_ERROR';
                break;
            case E_CORE_WARNING:
                $err_level = 'E_CORE_WARNING';
                break;
            case E_PARSE:
                $err_level = 'E_PARSE';
                break;
            case E_COMPILE_ERROR:
                $err_level = 'E_COMPILE_ERROR';
                break;
            case E_COMPILE_WARNING:
                $err_level = 'E_COMPILE_WARNING';
                break;
            default:
                $err_level = 'unknow level: ' . $err_level;
        }
	  
		return $err_level;
	}

	public static function getLevelExplain($err_level = 0){
		if (array_key_exists($err_level , self::$_errLevelInfoList)) {
			return self::$_errLevelInfoList[$err_level];
		} else {
			return 'Can not explain the Error Level，Level：' . $err_level;
		}
	}

	// 非调试模式（记录日志）
	public function nodebug($err_level , $err_msg , $err_file , $err_line , $err_ctx = null){
		$trace = debug_backtrace();

		array_shift($trace);

		$msg = $this->genErrStr($trace , $err_file , $err_line , $err_msg);

		// 记录日志
		$this->log($msg);

		if (E_USER_ERROR  ? true : false) {
			exit;
		}
	}

	public function debug($err_level , $err_msg , $err_file , $err_line , $err_ctx = null){
		$trace      = debug_backtrace();
		$err_level  = $this->getLevel($trace[0]['args'][0]);
		$err_msg    = $trace[0]['args'][1];
		$err_file   = $trace[0]['args'][2];
		$err_line   = $trace[0]['args'][3];

		array_shift($trace);

        $msg = $this->genErrStr($trace , $err_file , $err_line , $err_msg);

        // 记录日志
        $this->log($msg);

        echo $msg;
	}

	// 致命错误处理
	public function fetalDebug(){
		if (!is_null(error_get_last())) {
			$err_last = error_get_last();

            $msg = $this->genFetalErrStr($err_last['file'] , $err_last['line'] , $err_last['message']);

            $this->log($msg);

            exit($msg);
		}
	}

	// 致命错误
    public function fetalNoDebug(){
        if (!is_null(error_get_last())) {
            $err_last = error_get_last();

            $msg = $this->genFetalErrStr($err_last['file'] , $err_last['line'] , $err_last['message']);

            $this->log($msg);
        }
    }

}



