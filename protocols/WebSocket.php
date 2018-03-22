<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-21
 * Time: 下午1:43
 */

namespace Protocols;

class WebSocket
{
    // 是否握手
    protected $_shakeHands = false;

    // 如果是分割的数据包
    // 那么要按照发送顺序进行拼接
    protected $_data = '';

    // 消息仅支持文本数据发送
    // 加密: S -> C
    public function encode($data){
        $len = strlen($data);

        // 表示仅 支持文本 传输
        // 1000 0001 = 129
        $first_byte = chr(129);

        if ($len <= 125) {
            $second_byte = chr($len);
        } else if ($len <= 65535) {
            // 如果长度65535的时候, payload len = 126,后面 16 bit 被解释为无符号整数,大端字节序(对应的实际就是要发送给客户端的字符串的长度)
            $second_byte = chr(126) . pack('n' . $len);
        } else {
            // 如果长度已经超过 65535,payload_len = 127,后面 64bit 被解释为无符号整数,大端字节序
            $second_byte = chr(127) . pack('J' , $len);
        }

        // 加密后的数据
        $encode = $first_byte . $second_byte . $data;

        return $encode;
    }

    // 解密: C -> S
    public function decode($data){
        // 记住:位运算要求他们转化为数字!
        $first_byte     = ord($data[0]);
        $second_byte    = ord($data[1]);

        // 目前这边的一个缺陷是消息没办法处理分片

        // 判断是一个大数据包的一部分,还是一个完整的数据包
        $fin    = $first_byte >> 7;
        // 0000 1111 = 15 = 0xf
        // 操作码,实际无需理会即可
        $opcode = $first_byte & 15;
        // 0111 1111 = 127
        $payload_len = $second_byte & 127;

        if ($payload_len === 126) {
            // 最后 16bit 被解释为无符号整数,大端字节序
            $mask   = substr($data , 6 , 4);
            $encode = substr($data , 10);
        } else if ($payload_len === 127) {
            // 最后 64bit 被解释为无符号整数,大端字节序
            $mask   = substr($data , 10 , 4);
            $encode = substr($data , 14);
        } else {
            $mask   = substr($data , 2 , 4);
            $encode = substr($data , 6);
        }

        $decode = '';

        for ($i = 0; $i < strlen($encode); ++$i)
        {
            $decode .= $encode[$i] ^ $mask[$i % 4];
        }

        return $decode;
    }

    // 生成 Sec-Websocket-Accept 头字段
    public function genKey($sec_websocket_key){
        $sec_websocket_key = trim($sec_websocket_key);
        $guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

        $sec_websocket_accept = $sec_websocket_key . $guid;
        $sec_websocket_accept = sha1($sec_websocket_accept , true);
        $sec_websocket_accept = base64_encode($sec_websocket_accept);

        return $sec_websocket_accept;
    }

    // 请求头解析
    public function parseHeader($header){
        return http_request_header_parse($header);
    }

    // 获取请求头中的 Sec-WebSocket-Key
    public function getKey($header){
        $headers = $this->parseHeader($header);

        return $headers['Sec-WebSocket-Key'];
    }

    // 检查是否握手
    public function isShakeHand(){
        return $this->_shakeHands;
    }

    // 握手
    public function hand($header){
        $sec_websocket_key      = $this->getKey($header);
        $sec_websocket_accept   = $this->genKey($sec_websocket_key);

        // 生成握手响应头
        $header = $this->genHeader($sec_websocket_accept);

        // 设置握手状态
        $this->_shakeHands = true;

        return $header;
    }

    // 生成响应头
    public function genHeader($sec_websocket_key){
        $header  = "HTTP/1.1 101 Switching Protocols\r\n";
        $header .= "Connection: Upgrade\r\n";
        $header .= "Upgrade: websocket\r\n";
        $header .= "Sec-WebSocket-Accept: {$sec_websocket_key}\r\n";
        $header .= "\r\n";

        return $header;
    }

    // 心跳检查
    public function ping(){

    }

    public function pong(){

    }

}
