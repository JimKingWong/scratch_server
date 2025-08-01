<?php

namespace app\common\service;

use app\common\model\Cate;
use app\common\model\GoodsCate;
use app\common\model\Grid;
use app\common\model\Order;
use app\common\service\util\Scratch;

/**
 * 商品
 */
class Goods extends Base
{

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 获取商品列表
     */
    public function filter()
    {
        $cate_id = $this->request->param('cate_id');
        if($cate_id == ''){
            $this->error(__('无效参数'));
        }
        $where['cate_id'] = $cate_id;
        $where['status'] = '1';

        $cate = Cate::where('id', $cate_id)->field('id,name,title,intro,price,image')->find();

        $fields = "id,cate_id,goods_id";
        $list = GoodsCate::where($where)->field($fields)->order('weigh desc')->select();
        // dd($list);
        $goodsList = [];
        foreach($list as $val){
            $val->goods->image = $val->goods->image ? cdnurl($val->goods->image) : '';
            $goodsList[] = $val->goods;
        }
        
        $retval = [
            'cate' => $cate,
            'list' => $goodsList,
        ];
        $this->success(__('请求成功'), $retval);
    }

}