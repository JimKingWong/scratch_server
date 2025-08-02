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
        
        $retval = [
            'cate' => $cate,
            'list' => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }

}