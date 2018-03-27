<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午11:13
 */

require_once 'run.php';

// 当前这个执行文件仅仅是某个进程下运行的文件
// 如果接受到客户端链接，要求与某个其他进程下的客户端进行通信
// 那么需要将消息转发给父进程进行中转

// 如果是当前服务器下的，则无需


// redis 链接
$redis = new Redis();
$redis->connect('127.0.0.1' , '6379');
$redis->auth('364793');

$users = $redis->get('users');
$users = json_decode($users , true);

$app->on('open' , function(){
    var_dump("接受到客户端链接，进入子进程：" . app()->pid);
});

// 接受到消息的时候
$app->on('message' , function($data) use($redis){
    $data = json_decode($data , true);

    $users = $redis->get('users');
    $users = json_decode($users , true);

    if ($data['type'] == 'login') {
        $users[$data['id']] = [
            'username'  => $data['username'] ,
            'password'  => $data['password'] ,
            'machine'   => app()->identifier ,
            'address'   => gen_address(app()->config('forward.listen.parent')) ,
            'pid'       => app()->pid ,
            'cid'       => $this->id
        ];

        // 更新到 redis
        $users = json_encode($users);

        // 更新到 redis
        $redis->set('users' , $users);
        return ;
    }

    $other = $users[$data['to_id']];

    if ($other['machine'] == app()->identifier) {
        // 本机，且相同进程
        if ($other['pid'] == app()->pid) {
            var_dump("客户端连接意外的在相同进程里");

            app()->connectionsForClient[$other['cid']]->send($data['msg']);
        } else {
            // 其他进程
            $send = [
                'machine'   => $other['machine'] ,
                'address'   => '' ,
                'pid'       => $other['pid'] ,
                'cid'       => $other['cid'] ,
                'msg'       => $data['msg']
            ];

            $send = json_encode($send);

            var_dump("客户端不再同一个进程下，要父进程进行转发");
            app()->parent->send($send);
        }
    } else {
        var_dump("要发送到其他服务器上的客户端进程");
    }
});

$app->on('close' , function($id){
    var_dump("客户端已经断开链接");
});

$app->run();

function u(){
    return $GLOBALS['users'];
}

function has($id) {
    return isset(u()[$id]);
}