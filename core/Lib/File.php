<?php
/*
 * ****************************
 * author 陈学龙 2016-10-10
 * 文件/目录 操作类
 * ****************************
 */

namespace Core\Lib;


class File {
    private static $_instance = null;

    function __construct(){

    }

    public static function getInstance(){
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /*
     * 获取文件绝对路径
     * @param  String $path 文件路径（相对 | 绝对路径）
     * @return String
     */
    public static function getRelPath($path = ''){
        $path = format_path($path);
        $path = gbk($path);
        $path = realpath($path);

        if (!$path) {
            return false;
        }

        return utf8($path);
    }

    /*
     * 获取文件类型
     * @param String $file  文件路径
     * @return String
     */
    public static function getFileType($file = ''){
        $file = format_path($file);
        $file = gbk($file);

        if (!file_exists($file)) {
            return false;
        }

        if (is_dir($file)) {
            return 'dir';
        }

        return 'file';
    }

    /*
     * 检查文件是否存在
     * @param String $file 文件路径
     * @return true | false
     */
    public static function checkFile($file = ''){
        $file = format_path($file);
        $file = gbk($file);

        if (!file_exists($file)) {
            return false;
        }

        if (is_dir($file)) {
            return false;
        }

        return true;
    }

    /*
     * 检查目录
     * @param String             $dir       目录路径
     * @return true | false
     */
    public static function checkDir($dir = ''){
        $dir = format_path($dir);
        $dir = gbk($dir);

        if (is_dir($dir)) {
            return true;
        }

        return false;
    }

    /*
     * 创建目录
     * @param String   $dir          目录路径
     * @param Integer  $privilege    目录权限
     * @return true | false
     */
    public static function cDir($dir = '' , $privilege = 0755){
        if (!self::checkDir($dir)) {
            $dir = format_path($dir);
            $dir = gbk($dir);
            $mk  = mkdir($dir , $privilege);

            if (!$mk) {
                $err_msg  = "创建目录失败\r\n";
                $err_msg .= "待创建的目录路径： " . utf8($dir) . "\r\n";

                throw new \Exception($err_msg);
            }
        }

        return true;
    }

    /*
     * 直接根据路径创建文件
     * @param String   $dir          文件路径
     * @param Integer  $privilege    文件权限
     * @return true | false
     */
    public static function cFile($file = '' , $privilege = 0755){
        if (!self::checkFile($file)) {
            $file = format_path($file);
            $file = gbk($file);

            $f = fopen($file , 'x');

            if (!$f) {
                $err_msg  = "创建文件失败！" . "\r\n";
                $err_msg .= "待创建的文件路径： " . utf8($file) . "\r\n";

                throw new \Exception($err_msg);
            }

            if (!chmod($file , $privilege)) {
                $err_msg  = "设置文件权限失败" . "\r\n";
                $err_msg .= "待设置权限的文件路径： " . utf8($file) . "\r\n";

                throw new \Exception($err_msg);
            }

            fclose($f);
        }

        return true;
    }

    /*
     * 写入数据
     * @param	String       $file       待写入数据的文件路径
     * @param	$content     $contents   待写入的数据
     * @param   $write_type  $write_type 写入类型
     */
    public static function wData($file = '' , $content = '' , $write_type = null){
        if (!self::checkFile($file)) {
            self::cFile($file);
        }

        $file = format_path($file);
        $file = gbk($file);

        $write_type_range = array('a' , 'w');

        if (is_null($write_type)) {
            $write_type = 'a';
        }

        if (!in_array($write_type , $write_type_range)) {
            $err_msg  = "不支持的写入类型\r\n";
            $err_msg .= "当前提供的写入类型：" . $write_type . "\r\n";
            $err_msg .= "受支持的写入类型：" . implode(' ' , $write_type_range);

            throw new \Exception($err_msg);
        }

        $f = fopen($file , $write_type);

        if (!$f) {
            $err_msg  = "打开文件失败\r\n";
            $err_msg .= "待写入的文件路径：" . utf8($file) . "\r\n";

            throw new \Exception($err_msg);
        }

        if (flock($f , LOCK_EX)) {
            fwrite($f , $content);
            flock($f , LOCK_UN);
            fclose($f);

            return true;
        }

        $err_msg  = "文件已被占用\r\n";
        $err_msg .= "待锁定的文件路径：" . utf8($file) . "\r\n";

        throw new \Exception($err_msg);
    }

    /*
     * 获取分类后的 文件列表
     * 注意编码格式： utf8
     * 要想打开返回的结果中的文件 | 目录，需要转换为 gbk
     * @param  String        $dir          目录
     * @param  Boolean       $is_get_all   默认为：true 表示获取所有的内容
     * @return Array
     */
    private static function getTypeFileLists($dir = '' , $is_get_all = true){
        if (!self::checkDir($dir)){
            throw new \Exception("参数 1 不是目录： " . $dir);
        }

        $is_get_all = is_bool($is_get_all) ? $is_get_all : true;

        $get_list   = function($dir = '' , array &$result = array()) use(&$get_list , $is_get_all) {
            $dir = format_path($dir);
            $dir = gbk($dir);
            $d   = dir($dir);

            if (!$d) {
                throw new \Exception("无法打开当前目录：" . $dir);
            }

            while ($fname = $d->read())
            {
                if ($fname !== '.' && $fname !== '..'){
                    $fname = utf8($dir) . '/' . utf8($fname);

                    if (self::checkDir($fname)) {
                        $result['dir'][] = $fname . '/';

                        if ($is_get_all){
                            $get_list($fname , $result);
                        }
                    } else {
                        $result['file'][] = $fname;
                    }
                }
            }
        };

        $result = array(
            'dir'  => array() ,
            'file' => array()
        );

        $get_list($dir , $result);

        return $result;
    }

    /*
     * 获取目录下所有的目录 | 文件列表
     * @param  String   $dir   目录
     * @param  String   $type  目录类型
     * @return Array
     */
    public static function getFileLists($dir = '' , $type = 'mixed'){
        if (!self::checkDir($dir)) {
            throw new \Exception('参数 1 不是目录：' . $dir);
        }

        $type_range = array('file' , 'dir' , 'mixed');

        if (!in_array($type , $type_range)) {
            $type = 'mixed';
        }

        $list = self::getTypeFileLists($dir , true);

        switch ($type)
        {
            case 'mixed':
                $rel = array_merge($list['dir'] , $list['file']);
                break;
            case 'dir'  :
                $rel = $list['dir'];
                break;
            case 'file' :
                $rel = $list['file'];
                break;
        }

        return $rel;
    }

    // 获取目录下的一级 文件 | 目录列表
    public static function getFileList($dir = '' , $type = 'mixed'){
        if (!self::checkDir($dir)) {
            throw new \Exception('参数 1 不是目录：' . $dir);
        }

        $type_range = array('file' , 'dir' , 'mixed');

        if (!in_array($type , $type_range)) {
            $type = 'mixed';
        }

        $list = self::getTypeFileLists($dir , false);

        switch ($type)
        {
            case 'mixed':
                $rel = array_merge($list['dir'] , $list['file']);
                break;
            case 'dir'  :
                $rel = $list['dir'];
                break;
            case 'file' :
                $rel = $list['file'];
                break;
        }

        return $rel;
    }

    // 删除单个文件
    public static function dFile($file = ''){
        if (self::checkFile($file)) {
            $file = format_path($file);
            $file = gbk($file);

            if (!unlink($file)) {
                throw new \Exception('删除文件失败： ' . utf8($file));
            }
        }

        return true;
    }

    // 删除所有文件
    public static function dFiles($dir = ''){
        if (!self::checkDir($dir)) {
            throw new \Exception('参数 1 不是目录：' . $dir);
        }

        $rel = self::getTypeFileLists($dir);

        $failed = array(
            'file' => array() ,
            'dir'  => array()
        );

        $dir = gbk($dir);

        // 第一步：删除目录下的文件
        foreach ($rel['file'] as $v)
        {
            $v = gbk($v);

            if(!unlink($v)) {
                $failed['file'][] = utf8($v);
            }
        }

        // 第二步：删除目录
        foreach ($rel['dir'] as $v)
        {
            $v = gbk($v);

            if(!rmdir($v)) {
                $failed['dir'][] = utf8($v);
            }
        }

        if (!empty($failed['file']) || !empty($failed['dir'])) {
            throw new \Exception('部分文件删除失败：' . json_encode($failed));
        }

        return true;
    }

    /*
     * 保存网络文件
     * @param  String  $url   网络文件路径
     * @param  String  $dir   本地保存目录
     * @param  String  $fname 文件名
     */
    public static function saveUrlFile($url = '' , $dir = '' , $fname = ''){
        if (empty($url)) {
            return false;
        }

        $net_stream = @fopen($url , 'r');

        if ($net_stream === false) {
            return false;
        }

        $filename  = get_filename($url);
        $extension = get_extension($url);

        if (!self::checkDir($dir)) {
            self::cDir($dir);
        }

        $dir = realpath($dir);

        $cur_time = date('Y-m-d H-i-s' , time());

        if (empty($fname)) {
            $fname = 'url-' . $cur_time . '.' . $extension;
        } else {
            $fname .= '.' . $extension;
        }

        $dir		 = format_path($dir);
        $file        = $dir . '/' . $fname;

        if (!self::checkFile($file)) {
            $file_stream = @fopen(gbk($file) , 'x');

            // 创建本地文件失败
            if ($file_stream === false) {
                return false;
            }

            // 写入数据
            while ($line = fgets($net_stream))
            {
                fwrite($file_stream , $line);
            }
        }

        return [
            'local_path' => $file ,
            'url'		 => generate_url($file)
        ];
    }

    final protected function __clone(){
        throw new \Exception('不允许克隆');
    }

}


