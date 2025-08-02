<?php

namespace app\common\service;

use app\common\model\Cate;
use app\common\model\MoneyLog;
use app\common\model\Order;
use app\common\model\User;
use fast\Random;
use think\Cache;
use think\Db;
use think\Log;

/**
 * 游戏服务
 */
class Game extends Base
{

    protected $model = null;
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 购买
     */
    public function buy()
    {
        $cate_id = $this->request->post('cate_id');

        $cate = Cate::where('id', $cate_id)->find();
        if (!$cate) {
            $this->error(__('游戏不存在'));
        }

        $user = $this->auth->getUser();

        $check_order = db('order')->where('cate_id', $cate_id)->where('user_id', $this->auth->id)->where('status', 1)->field('id,order_no,grid')->find();
        
        if ($check_order) {
            $retval = [
                'order_no' => $check_order['order_no'],
                'money'    => number_format($user->money, 2),
                'grid'     => json_decode($check_order['grid'], true),
            ];

            $this->success(__('您本次已购买过该游戏'), $retval);
        }

        
        if($user->money < $cate->price){
            $this->error(__('余额不足'));
        }

        $order_no = date('YmdHis') . mt_rand(100000, 999999);

        $redis = Cache::store('redis')->handler();
        // 并发处理
        $lock_key = 'buy:lock_' . $this->auth->id;

        $is_lock = $redis->setnx($lock_key, 1); // 加锁

        if(!$is_lock){
            // 释放锁
            // $redis->del($lock_key);
            $this->error(__('请勿重复提交'));
        }

        $winItem = db('goods_cate')->where('cate_id', $cate_id)->field('id,name,price,odds,is_win,image')->select();

        $arr = [];
        $goods = [];
        foreach($winItem as $v){
            $arr[$v['id']] = $v['odds'] * 10000;
            $goods[$v['id']] = $v;
        }
        
        // 抽中的格子
        $goods_id = Random::lottery($arr);
        $goods = $goods[$goods_id];
        // dd($goods);

        // 获取九宫格
        $grid = $this->getGoodsGrid($cate_id, $goods);
        
        if($is_lock){
            $result = false;
            Db::startTrans();
            try{
                $user = User::lock(true)->find($user->id);

                // 避免并发超出
                if($user->money < $cate->price){
                    Db::rollback();
                    $this->error(__('余额不足'));
                }
                $before = $user->money;
                $after = $user->money - $cate->price;
                $user->money = $after;

                $result = $user->save();

                if(MoneyLog::create([
                    'admin_id'          => $user->admin_id,
                    'user_id'           => $user->id,
                    'type'              => 'buy_goods',
                    'before'            => $before,
                    'after'             => $after,
                    'money'             => $cate->price,
                    'memo'              => '购买游戏',
                    'transaction_id'    => $order_no,
                ]) === false){
                    $result = false;
                }

                // 创建订单并生成对应订单的九宫格
                $data = [
                    'admin_id'  => $user->admin_id,
                    'user_id'   => $user->id,
                    'cate_id'   => $cate_id,
                    'order_no'  => $order_no,
                    'status'    => 1,
                    'num'       => 1,
                    'is_win'    => $goods['is_win'],
                    'goods_cate_id' => $goods_id,
                    'grid'      => json_encode($grid),
                    'price'     => $cate->price,
                    'paytime'   => datetime(time()),
                ];
                
                if(Order::create($data) === false){
                    $result = false;
                }
                
                if($result != false){
                    Db::commit();
                }
                
            }catch(\Exception $e) {
                Log::record($e->getMessage());
                // 释放锁
                $redis->del($lock_key);
                Db::rollback();
                $this->error(__('购买失败'));
            }

            // 释放锁
            $redis->del($lock_key);
        }else{
            // 防止死锁
            if($redis->ttl($lock_key) == -1){
                $redis->expire($lock_key, 1);
            }
        }
        
        if($result === false){
            $this->error(__('购买失败'));
        }

        $retval = [
            'order_no' => $order_no,
            'money'    => number_format($user->money, 2),
            'grid'     => $grid,
        ];

        $this->success(__('请求成功'), $retval);
    }
    
    /**
     * 检查是否可以玩
     */
    public function checkPlay($user, $cate_id)
    {
        $where['user_id'] = $user->id;
        $where['cate_id'] = $cate_id;
        $where['status'] = 1;
        $order = Order::where($where)->find();
        if (!$order) {
            $this->error(__('请先购买'));
        }
        
        return $order;
    }
   
    /**
     * 开始游戏
     */
    public function play()
    {
        $cate_id = $this->request->post('cate_id');

        $user = $this->auth->getUser();

        // 当前订单数据
        $order = $this->checkPlay($user, $cate_id);
    
        $grid = json_decode($order['grid'], true);
        
        $goods_id = $order['goods_cate_id'];

        $is_win = $order['is_win'];

        $goods = db('goods_cate')->where('id', $goods_id)->field('id,name,price,image')->find();
        $goods['price'] = number_format($goods['price'], 2);
        $goods['image'] = $goods['image'] ? cdnurl($goods['image']) : '';
        
        $roundid = $user->id . '_' . $cate_id . '_' . date('YmdHis');

        $redis = Cache::store('redis')->handler();
        // 并发处理
        $lock_key = 'play:lock_' . $this->auth->id;

        $is_lock = $redis->setnx($lock_key, 1); // 加锁
        // dd($is_lock);
        if(!$is_lock){
            // 释放锁
            // $redis->del($lock_key);
            $this->error(__('请勿重复提交'));
        }

        if($is_lock){
            $result = false;
            Db::startTrans();
            try{
                $user = User::lock(true)->find($user->id);
                
                $data = [
                    'admin_id'  => $user->admin_id,
                    'user_id'   => $user->id,
                    'cate_id'   => $cate_id,
                    'roundid'   => $roundid,
                    'status'    => 1,
                    'goods_cate_id' => $goods_id,
                    'is_win'        => $is_win,
                    'prizes'    => json_encode($goods),
                    'win_amount'=> $goods['price'],
                    'createtime'=> datetime(time()),
                    'updatetime'=> datetime(time()),
                    'endtime'   => datetime(time()),
                ];
                $result = db('game_record')->insert($data);

                if(db('order')->where('id', $order['id'])->update(['status' => 2]) === false){
                    $result = false;
                }
            
                if($is_win > 0){
                    $before = $user->money;
                    $after = $user->money + $goods['price'];
                    $user->money = $after;
                    $user->bonus = $user->bonus + $goods['price'];

                    if($user->save() === false){
                        $result = false;
                    }

                    $user->userdata->total_profit = $user->userdata->total_profit + $goods['price'];
                    $user->userdata->today_profit = $user->userdata->today_profit + $goods['price'];
                    if($user->userdata->save() === false){
                        $result = false;
                    }

                    if(MoneyLog::create([
                        'admin_id'          => $user->admin_id,
                        'user_id'           => $user->id,
                        'type'              => 'lottery',
                        'before'            => $before,
                        'after'             => $after,
                        'money'             => $goods['price'],
                        'memo'              => '刮刮乐中奖',
                        'transaction_id'    => $roundid,
                    ]) === false){
                        $result = false;
                    }
                }
                
                if($result != false){
                    Db::commit();
                }

            }catch(\Exception $e){
                $redis->del($lock_key);
                Log::record($e->getMessage());
                Db::rollback();
                $this->error(__('请求失败'));
            }

            // 释放锁
            $redis->del($lock_key);
        }else{
            // 防止死锁
            if($redis->ttl($lock_key) == -1){
                $redis->expire($lock_key, 1);
            }
        }
        
        $retval = [
            'money'      => number_format($user->money, 2),
            'award_item' => $goods,
            'grid'       => $grid,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取九宫格
     */
    public function getGoodsGrid($cate_id, $goods)
    {
        $fields = "id,cate_id,goods_id,name,abbr,image,price";
        if($goods['is_win'] > 0){
            // 中奖显示的格子
            $winItem = db('goods_cate')->where('cate_id', $cate_id)->where('is_win', 1)->where('id', $goods['id'])->field($fields)->find();

            $winItem = [$winItem, $winItem, $winItem];

            $noWinItem = db('goods_cate')->where('cate_id', $cate_id)->where('is_win', 1)->where('id', '<>', $goods['id'])->field($fields)->orderRaw("rand()")->limit(6)->select();
        }else{
            // 未中奖显示的格子
            $winItem = db('goods_cate')->where('cate_id', $cate_id)->where('is_win', 1)->field($fields)->orderRaw("rand()")->limit(1)->select();

            $noWinItem = db('goods_cate')->where('cate_id', $cate_id)->where('is_win', 1)->field($fields)->orderRaw("rand()")->limit(8)->select();
        }
        
        $grid = array_merge($winItem, $noWinItem);

        // 打乱
        shuffle($grid);
        
        foreach($grid as $k => $v){
            $grid[$k]['image'] = cdnurl($v['image']);
        }

        return $grid;
    }
}