<?php
/**
 * Created by PhpStorm.
 * User: grayvtouch
 * Date: 18-3-26
 * Time: 下午8:08
 */

namespace Event\EventCtrl;

use Event\Select;

class SelectCtrl implements EventCtrl
{
    public $id = null;

    function __construct(string $id = ''){
        $this->id = $id;
    }

    public function destroy(){
        Select::destroy($this->id);
    }
}