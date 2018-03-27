<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 上午10:06
 */

$pids = [];

for ($i = 0; $i < 3; ++$i)
{
    $pid = pcntl_fork();

    if ($pid < 0) {
        throw new Exception("创建子进程失败");
    } else if ($pid > 0) {
        $pids[] = $pid;
    } else {
        var_dump("子进程PID:" . posix_getpid());
        exit;
    }
}

foreach ($pids as $v)
{
    pcntl_waitpid($v , $status);
}

echo "子进程ID:\n";

print_r($pids);

exit("\n进程全部推出\n");