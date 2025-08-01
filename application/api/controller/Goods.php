<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * Goods
 * 
 */
class Goods extends Api
{
    protected $noNeedLogin = ['filter'];
    protected $noNeedRight = ['*'];

    public function filter()
    {
        $service = new \app\common\service\Goods();
        $service->filter();
    }

    
}
