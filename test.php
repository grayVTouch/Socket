<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-4-2
 * Time: 下午4:28
 */

require_once 'core/Function/base.php';
require_once 'event/EventCtrl/EventCtrl.php';
require_once 'event/EventCtrl/EvCtrl.php';
require_once 'event/Event.php';
require_once 'event/Ev.php';

use Event\Event;
use Event\Ev;

ini_set('display_errors' , 'On');
error_reporting(E_ALL);

$pids = [];

for ($i = 0; $i < 4; ++$i)
{
    $pid = pcntl_fork();

    if ($pid < 0) {
        throw new Exception("创建子进程失败");
    } else if ($pid > 0) {
        $pids[] = $pid;
    } else {
        Ev::clear();

        $socket = stream_socket_server('tcp://127.0.0.1:9005', $errno , $errstr , STREAM_SERVER_BIND | STREAM_SERVER_LISTEN ,  stream_context_create([
            'socket' => [
                'so_reuseport' => true ,
                'backlog' => 10000
            ]
        ]));

        Ev::addIo($socket , Event::READ , function($ctrl , $socket){

            $client = stream_socket_accept($socket);

            var_dump("接受到客户端链接-------");

            Ev::addIo($client , Event::READ , function($ctrl , $socket){
                $msg = fread($socket , 65535);

                var_dump('接收到客户端数据:' . $msg);

                if (empty($msg)) {
                    $ctrl->destroy();
                    return ;
                }
            });
        });

        Ev::loop();
        exit;
    }
}

foreach ($pids as $v)
{
    pcntl_waitpid($v , $status);
}

echo "所有进程退出成功\n";