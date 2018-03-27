<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-25
 * Time: 上午11:41
 */


require_once './core/Function/base.php';
require_once './event/Event.php';
require_once './event/Select.php';

use Event\Event;
use Event\Select;

$count = 1;

Select::addLoopTimer(2 , true , function() use(&$count){
    $count++;

    var_dump("每隔 2s 触发一次,已触发次数:{$count}");
});

Select::addTimer(1 , true , function() use(&$count){
    $count++;

    var_dump("1s 后触发");
});

Select::loop();