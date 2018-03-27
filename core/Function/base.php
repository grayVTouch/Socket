<?php
/*
 * 按要求返回随机数
 * @param  Integer    $len        随机码长度
 * @param  String     $type       随机码类型  letter | number | mixed
 * @return Array
 */
function random($len = 4 , $type = 'mixed' , bool $is_return_string = false){
    $type_range = array('letter','number','mixed');

    if (!in_array($type , $type_range)){
        throw new Exception('参数 2 类型错误');
    }

    if (!is_int($len) || $len < 1) {
        $len = 1;
    }

    $result = [];
    $letter = array('a' , 'b' , 'c' , 'd' , 'e' , 'f' , 'g' , 'h' , 'i' , 'j' , 'k' , 'l' , 'm' , 'n' , 'o' , 'p' , 'q' , 'r' , 's' , 't' , 'u' , 'v' , 'w' , 'x' , 'y' , 'z');

    for ($i = 0; $i < count($letter) - $i; ++$i)
    {
        $letter[] = strtoupper($letter[$i]);
    }

    if ($type === 'letter'){
        for ($i = 0; $i < $len; ++$i)
        {
            $rand = mt_rand(0 , count($letter) - 1);

            shuffle($letter);

            $result[] = $letter[$rand];
        }
    }

    if ($type === 'number') {
        for ($i = 0; $i < $len; ++$i)
        {
            $result[] = mt_rand(0 , 9);
        }
    }

    if ($type === 'mixed'){
        for ($i = 0; $i < $len; ++$i)
        {
            $mixed = [];
            $rand  = mt_rand(0 , count($letter) - 1);

            shuffle($letter);

            $mixed[] = $letter[$rand];
            $mixed[] = mt_rand(0,9);

            $rand = mt_rand(0 , count($mixed) - 1);

            shuffle($mixed);

            $result[] = $mixed[$rand];
        }
    }

    return $is_return_string ? implode('' , $result) : $result;
}

/*
 * 判断是否是无效值
 * @param  Mixed  $val
 * @return Boolean
 */
function is_valid($val){
    // 未定义变量
    if (!isset($val)) {
        return false;
    }

    // null
    if (is_null($val)) {
        return false;
    }

    // boolean false
    if ($val === false) {
        return false;
    }

    // 空值
    if ($val === '') {
        return false;
    }

    return true;
}

/*
 * 数组过滤：null 或 空字符串单元
 * @param   Array     $arr
 * @param   Boolean   $is_recursive  是否递归过滤
 * @return  Array    过滤后的数组
 */

function filter_arr(Array $arr = [] , $is_recursive = false){
    if (empty($arr)) {
        return $arr;
    }

    $is_recursive = is_bool($is_recursive) ? $is_recursive : false;

    $filter = function(Array $arr = [] , Array &$rel = []) use (&$filter , $is_recursive) {
        if (empty($arr)) {
            return true;
        }

        if (!$is_recursive) {
            foreach ($arr as $k => $v)
            {
                if (is_valid($v)) {
                    $rel[$k] = $v;
                }
            }
        } else {
            foreach ($arr as $k => $v)
            {
                if (is_array($v) && empty($v)) {
                    continue;
                }

                if (is_array($v) && !empty($v)) {
                    $rel[$k] = [];
                    $filter($v , $rel[$k]);
                } else {
                    if (is_valid($v)) {
                        $rel[$k] = $v;
                    }
                }
            }
        }
    };

    $rel = [];

    $filter($arr , $rel);

    return $rel;
}


/*
 * 编码转换 gb2312 -> utf-8
 * @param String $string
 * @return String
 */
function utf8($string = ''){
    return mb_convert_encoding($string , 'utf-8' , 'gb2312');
}

/*
 * 编码转换 utf-8 -> gb2312
 * @param String $string
 * @return String
 */
function gbk($string = ''){
    return mb_convert_encoding($string , 'gb2312' , 'utf-8');
}

// 导入数组单元到全局变量 ，并检查是否已存在，若存在则报错
function extract_global(Array $var_list = []){
    if (empty($var_list)) {
        return true;
    }

    foreach ($var_list as $k => $v)
    {
        if (isset($GLOBALS[$k])) {
            throw new Exception('已存在全局变量： ' . $k);
        }

        $GLOBALS[$k] = $v;
    }
}

/*
 * 给函数绑定参数
 * @param   Callable $func 待绑定参数的函数
 * @return  Closure
 */
function func_bind_args(Callable $func = null){
    $args = func_get_args();

    array_shift($args);

    return function() use($func , $args){
        return call_user_func_array($func , $args);
    };
}

/*
 * 获取当前使用平台：Pc / Mobile
 */
function get_platform(){
    $user_agent   = $_SERVER['HTTP_USER_AGENT'];
    $platform_reg = "/mobile/i";

    if (preg_match($platform_reg , $user_agent , $result) === 1) {
        return 'Mobile';
    }

    return 'Pc';
}

// 获取web服务器名称
function get_web_server(){

    $s = $_SERVER['SERVER_SOFTWARE'];
    $s_idx = 0;
    $e_idx = mb_strrpos($s , ' ');
    $server = mb_substring($s , $s_idx , $e_idx);

    return empty($server) ? $_SERVER['SERVER_SOFTWARE'] : $server;
}

/*
 * 判断一个数是偶数还是奇数
 */
function odd_even($num = 0){
    if (!is_numeric($num)) {
        throw new \Exception('参数 1 类型错误');
    }

    $b = 2;

    if ($num % $b !== 0) {
        return 'odd';
    }

    return 'even';
}

/**
 * 根据不同的服务器环境获取请求头
 * 目前支持的服务器有：Apache/Nginx
 * @param String $key 请求头
 * @return Boolean|String 失败时返回 false
 */
function get_request_header($key = ''){
    if (empty($key)) {
        return false;
    }

    if (function_exists('getallheaders')) {
        // Apache 服务器
        $headers = getallheaders();

        foreach ($headers as $k => $v)
        {
            if ($k === $key) {
                return $v;
            }
        }
    } else {
        // nginx 服务器
        $key = str_replace('-' , '_' , $key);
        $key = 'HTTP_' . $key;

        // Nginx 服务器
        foreach ($_SERVER as $k => $v)
        {
            if ($k === $key) {
                return $v;
            }
        }
    }

    return false;
}

