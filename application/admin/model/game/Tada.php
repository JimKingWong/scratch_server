<?php

namespace app\admin\model\game;

use think\Model;
use traits\model\SoftDelete;

class Tada extends Model
{

    use SoftDelete;

    protected $resultSetType = 'collection';

    // 表名
    protected $name = 'game_tada';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'Y-m-d H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'game_type_text',
        'status_text',
        'is_works_text'
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

    
    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }

    public function getGameTypeList()
    {
        return ['1' => __('Game_type 1'), '2' => __('Game_type 2'), '3' => __('Game_type 3'), '5' => __('Game_type 5'), '8' => __('Game_type 8')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getIsWorksList()
    {
        return ['0' => __('Is_works 0'), '1' => __('Is_works 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? '';
    }


    public function getGameTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['game_type'] ?? '');
        $list = $this->getGameTypeList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getIsWorksTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_works'] ?? '');
        $list = $this->getIsWorksList();
        return $list[$value] ?? '';
    }




}
