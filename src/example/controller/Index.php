<?php
namespace app\{MODULE_NAME}\controller;

use xqkeji\controller\Base;

class Index extends Base
{
    public function index()
    {
        return $this->display('{MODULE_NAME}/index');
    }
}
