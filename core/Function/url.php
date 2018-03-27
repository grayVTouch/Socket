<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-21
 * Time: 下午2:23
 */

/**
 * 请求头解析
 * @param $http_header 请求头
 */
function http_request_header_parse($http_header){
    $lines      = explode("\r\n" , $http_header);
    $first_line = array_shift($lines);
    $first_line = explode(' ' , $first_line);

    // 请求头
    $headers = [];

    $headers['method']      = $first_line[0];
    $headers['uri']         = $first_line[1];
    $headers['protocol']    = $first_line[2];

    foreach ($lines as $v)
    {
        $line = explode(':' , $v);

        if (count($line) < 2) {
            continue ;
        }

        $line[0] = trim($line[0]);
        $line[1] = trim($line[1]);

        $headers[$line[0]] = $line[1];
    }

    return $headers;
}