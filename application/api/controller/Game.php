<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * Game
 */
class Game extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    public function buy()
    {
        $service = new \app\common\service\Game();
        $service->buy();
    }

    /**
     * 创建游戏
     */
    public function create()
    {
        $service = new \app\common\service\Game();
        $service->create();
    }

    /**
     * 游戏开始
     */
    public function play()
    {
        $service = new \app\common\service\Game();
        $service->play();
    }
}
