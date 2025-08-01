<?php

namespace app\admin\model\game;

use think\Model;
use traits\model\SoftDelete;

class Goodscate extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'goods_cate';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'is_win_text',
        'status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }

    
    public function getIsWinList()
    {
        return ['0' => __('Is_win 0'), '1' => __('Is_win 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getIsWinTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_win'] ?? '');
        $list = $this->getIsWinList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




    public function cate()
    {
        return $this->belongsTo('app\admin\model\Cate', 'cate_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
