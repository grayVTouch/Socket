<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午11:41
 */

$pair = stream_socket_pair(STREAM_PF_UNIX , STREAM_SOCK_STREAM , STREAM_IPPROTO_IP);

$pid = pcntl_fork();

if ($pid < 0) {
    throw new Exception("创建子进程失败");
} else if ($pid === 0) {
    fclose($pair[0]);

    $parent = $pair[1];

    stream_set_blocking($parent , false);

    while (true)
    {
        $read = [$parent];
        $write = $read;
        $except = [];

        stream_select($read , $write , $except , 0 , 0);

        foreach ($read as $v)
        {
            $msg = fread($v , 65535);

            if (!empty($msg)) {
                echo "接受到父进程消息:" . $msg . PHP_EOL;
                fwrite($v , 'child send msg');
            }
        }

        sleep(1);
    }
} else {
    fclose($pair[1]);

    $child = $pair[0];

    stream_set_blocking($child , false);

    while (true)
    {
        $read = [$child];
        $write = $read;
        $except = [];

        stream_select($read , $write , $except , 0 , 0);

        foreach ($read as $v)
        {
            $msg = fread($v , 65535);

            if (!empty($msg)) {
                echo "接受到子进程消息:" . $msg . PHP_EOL;
            }
        }

        fwrite($child , 'parent send msg');

        sleep(1);
    }
}