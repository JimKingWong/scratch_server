<?php

namespace app\common\model;

use think\Model;

class Record extends Model
{

    protected $resultSetType = 'collection';
    

    // 表名
    protected $name = 'game_record';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    protected $dateFormat = 'm/d/Y H:i:s';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';


   
}
