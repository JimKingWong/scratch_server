<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;

class GoodsCate extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'goods_cate';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';


    public function goods()
    {
        return $this->hasOne('Goods', 'id', 'goods_id')->field('name,abbr,image,price');
    }

    public function cate()
    {
        return $this->hasOne('Cate', 'cate_id', 'id');
    }
}
