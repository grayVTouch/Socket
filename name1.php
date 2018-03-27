<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午10:44
 */

$tcp = 'tcp://127.0.0.1:9005';

$client = stream_socket_client($tcp , $errno , $errstr);