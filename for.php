<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 下午5:53
 */

require_once './core/Function/base.php';

spl_autoload_register(function($classname){
    $classname = lcfirst($classname);
    $classname = ltrim($classname , '\\/');

    $file = __DIR__ . '/' . $classname . '.php';
    $file = str_replace('\\' , '/' , $file);

    // var_dump($file);

    if (file_exists($file)) {
        require_once $file;
    }
});


use Event\Ev;
use Event\Select;
use Event\Event;
use Event\EvCtrl\EventCtrl;

$count = 1;

/*
Select::addLoopTimer(1 , true , function(EventCtrl $o) use(&$count){
    var_dump("每隔1s执行一次");

    $count++;

    if ($count > 5) {
        $o->stop();
    }
});

Select::loop();
*/


Ev::addLoopTimer(2 , true , function(EventCtrl $o) use(&$count){
    $count++;

    var_dump("每隔 2s 触发一次");

    if ($count > 5) {
        $o->stop();
    }
});

Ev::addTimer(2 , true , function(EventCtrl $o){
    var_dump('2s 后触发,每隔1s触发一次');
});

Ev::loop();