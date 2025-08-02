<?php

namespace app\common\service;

use app\common\model\Cate;
use app\common\model\GoodsCate;

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
        $cate->image = $cate->image ? cdnurl($cate->image) : '';

        $fields = "id,cate_id,goods_id,image,price";
        $where['is_win'] = 1;
        $list = GoodsCate::where($where)->field($fields)->order('weigh desc')->select();
        // dd($list);

        foreach($list as $val){
            $val->image = $val->image ? cdnurl($val->image) : '';
        }

        if(isset($this->auth->id)){
            $map['user_id'] = $this->auth->id;
        }
        $map['cate_id'] = $cate_id;
        $map['status'] = 1;
        $order = db('order')->where($map)->find();

        $is_order = 0;
        $grid = [];
        if($order){
            $is_order = 1;
            $grid = json_decode($order['grid'], true);
        }
            
        $retval = [
            'cate' => $cate,
            'list' => $list,
            'is_order' => $is_order,
            'grid'     => $grid,
        ];
        $this->success(__('请求成功'), $retval);
    }

}