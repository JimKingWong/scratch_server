<?php

namespace app\admin\model\game;

use think\Model;


class Record extends Model
{

    

    

    // 表名
    protected $name = 'game_record';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_win_text',
        'status_text'
    ];
    

    
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




    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
