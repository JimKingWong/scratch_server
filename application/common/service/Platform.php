<?php

namespace app\common\service;

use app\common\model\Cate;
use app\common\model\Custservice;
use think\Cache;
use think\Db;

/**
 * 平台服务
 */
class Platform extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 首页初始化
     */
    public function init()
    {
        $cate = Cate::where('status', 1)
            ->where('pid', 0)
            ->order('weigh desc')
            ->field('id,name,title,intro,price,image')
            ->cache(true, 86400)
            ->select();
        foreach ($cate as $val) {
            $val->image = $val->image ? cdnurl($val->image) : '';
            $val->price = number_format($val->price, 2);
        }

        // 中奖记录
        $users = db('user')->where('is_test', 0)->column('username', 'id');
        $record = db('game_record')->where('is_win', 1)->orderRaw("rand()")->limit(20)->select();
        $awards = [];
        $k = 0;
        if(count($record) < 20){
            // 中间记录比较少时, 用假数据
            $game_goods = db('goods_cate')->where('status', 1)->field('name,price,image')->orderRaw("rand()")->limit(20)->select();
            foreach($game_goods as $val){
                $awards[$k]['username'] = isset($users[array_rand($users)]) ? dealUsername($users[array_rand($users)]) : dealUsername('unknown');
                $awards[$k]['goods_name'] = $val['name'];
                $awards[$k]['goods_price'] = $val['price'];
                $awards[$k]['goods_image'] = $val['image'] ? cdnurl($val['image']) : '';
                $k ++;
            }
        }else{
            $goods = db('goods_cate')->column('name,price,image', 'id');
            foreach($record as $val){
                $awards[$k]['username'] = isset($users[$val['user_id']]) ? dealUsername($users[$val['user_id']]) : dealUsername('unknown');
                $awards[$k]['goods_name'] = $goods[$val['goods_cate_id']]['name'];
                $awards[$k]['goods_price'] = $goods[$val['goods_cate_id']]['price'];
                $awards[$k]['goods_image'] = $goods[$val['goods_cate_id']]['image'] ? cdnurl($goods[$val['goods_cate_id']]['image']) : '';
                $k ++;
            }
        }
        

        $retval = [
            'cate'      => $cate,
            'awards'    => $awards
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 客服
     */
    public function support()
    {
        $data = Cache::get('custservice');
        if(!$data){
            $custservice = Custservice::where('status', 1)->order('weigh desc')->field('id,name,channel,image,content,url')->select();

            $config = config('platform');

            $data = [
                'system_service'    => $config['system_service'],
                'group_telegram'    => $config['group_telegram'],
                'group_whatsapp'    => $config['group_whatsapp'],
                'group_ins'         => $config['group_ins'],
                'custservice'       => $custservice
            ];
            Cache::set('custservice', $data, 86400);
        }
        
        $this->success(__('请求成功'), $data);
    }

    /**
     * 获取语言列表
     */
    public function lang()
    {
        $language = db('language')->where('status', 1)->order('weigh desc')->field('id,name,title,is_default')->cache(true)->select();
        
        $retval = [
            'language' => $language,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 站内信
     */
    public function letter()
    {
        $type = $this->request->get('type', 0);

        $where['type'] = $type;

        $user_id = $this->auth->id ?? 0;

        $fields = "id,title,content,createtime,type";
        $list = \app\common\model\Letter::field($fields)->order('id desc');

        if($type != 1){
            $list = $list->where($where);
        }
        
        if($type == 1){
            if($user_id > 0){
                $list = $list->where($where);
            }
            $list = $list->where([
                ['EXP', Db::raw("FIND_IN_SET(". $user_id .", user_ids)")]
            ]);
        }
        
        $list = $list->select();

        $readList = \app\common\model\LetterRead::where('user_id', $this->auth->id)->column('id', 'letter_id');
        foreach($list as $val){
            $val->is_read = isset($readList[$val->id]) ? 1 : 0;
        }

        $this->success(__('请求成功'), $list);
    }

    
    /**
     * 站内信已读
     */
    public function read()
    {
        $letter_id = $this->request->get('letter_id', 0);
        
        $fields = "id,title,content,createtime,type";
        $letter = \app\common\model\Letter::field($fields)->find($letter_id);
        if(!$letter){
            $this->error(__('无效参数'));
        }

        $where['user_id'] = $this->auth->id;
        $where['letter_id'] = $letter_id;
        $check = \app\common\model\LetterRead::where($where)->find();
        if(!$check){
            $data = [
                'user_id'       => $this->auth->id,
                'letter_id'     => $letter_id,
                'is_read'       => 1,
            ];
            \app\common\model\LetterRead::create($data);
        }

        $this->success(__('请求成功'), $letter);
    }
}