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
        'Protocol\\'    => PROTOCOL_DIR
    ] ,
    'file'  => [
        // 工具函数
        CORE_DIR . 'Function/array.php' ,
        CORE_DIR . 'Function/base.php' ,
        CORE_DIR . 'Function/file.php' ,
        CORE_DIR . 'Function/url.php' ,
        CORE_DIR . 'Function/time.php' ,

        // 系统必须函数
        CORE_DIR . 'Common/currency.php' ,
        CORE_DIR . 'Common/tool.php' ,
    ]
]);