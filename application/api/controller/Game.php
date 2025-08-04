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
     * 游戏开始
     */
    public function play()
    {
        $service = new \app\common\service\Game();
        $service->play();
    }

    /**
     * 测试rtp
     */
    public function testRtp()
    {
        $service = new \app\common\service\Game();
        $service->testRtp();
    }

    /**
     * 游戏记录
     */
    public function record()
    {
        $service = new \app\common\service\Game();
        $service->record();
    }

}
