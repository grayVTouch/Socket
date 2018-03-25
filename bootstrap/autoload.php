<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-23
 * Time: 下午3:53
 */

require_once CORE_DIR . 'Autoload.php';

use Core\Autoload;

$autoload = new Autoload();

$autoload->register([
    'class' => [
        'Connection\\'  => CONNECTION_DIR ,
        'Core\\'        => CORE_DIR ,
        'Event\\'       => EVENT_DIR ,
        'Protocol\\'    => PROTOCOL_DIR ,
    ] ,
    'file'  => [
        FUNCTION_DIR . 'array.php' ,
        FUNCTION_DIR . 'url.php' ,
    ]
]);