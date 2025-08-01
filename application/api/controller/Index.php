<?php

namespace app\api\controller;

use app\common\controller\Api;


/**
 * 首页接口
 * @ApiInternal
 * 
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success(__('请求成功'));
    }

    /**
     * 部署第一步
     */
    private function startup()
    {
        // 创建es
        $service = new \app\common\service\util\Startup;
        // 创建es
        // $service::createEs();

        // 清理数据库数据
        // $service::clearData();

    }

}
