<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 下午7:54
 */

namespace Event\EvCtrl;

use Event\Ev;

class EvCtrl implements EventCtrl
{
    // 事件 id
    public $id = null;

    function __construct($watcher , $id){
        $this->id       = $id;
        $this->watcher  = $watcher;
    }

    // 停止
    public function stop(){
        // 停止事件
        $this->watcher->stop();

        // 删除事件
        Ev::delete($this->id);
    }
}