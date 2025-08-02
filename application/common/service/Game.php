<?php

namespace app\common\service;

use app\common\model\Cate;
use app\common\model\MoneyLog;
use app\common\model\Order;
use app\common\model\User;
use fast\Random;
use think\Db;

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
        
        if($user->money < $cate->price){
            $this->error(__('余额不足'));
        }

        $order_no = date('YmdHis') . mt_rand(100000, 999999);

        $result = false;
        Db::startTrans();
        try{
            $user = User::lock(true)->find($user->id);
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

            $data = [
                'admin_id'  => $user->admin_id,
                'user_id'   => $user->id,
                'cate_id'   => $cate_id,
                'order_no'  => $order_no,
                'status'    => 1,
                'num'       => 1,
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
            Db::rollback();
            $this->error(__('购买失败'));
        }
        
        if($result === false){
            $this->error(__('购买失败'));
        }

        $this->success(__('请求成功'), ['order_no' => $order_no, 'money' => number_format($user->money, 2)]);
    }
    
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
     * 生成游戏
     */
    public function create()
    {
        $cate_id = $this->request->post('cate_id');

        $user = $this->auth->getUser();
        
        $this->checkPlay($user, $cate_id);

        $where['user_id'] = $user->id;
        $where['cate_id'] = $cate_id;
        $where['status'] = 0;
        $game = db('game_record')->where($where)->field('id,roundid')->find();

        if($game){
            $this->success(__('请求成功'), ['roundid' => $game['roundid'], 'money' => number_format($user->money, 2)]);
        }

        $roundid = $user->id . '_' . $cate_id . '_' . date('YmdHis');

        // 创建游戏
        $data = [
            'admin_id'  => $user->admin_id,
            'user_id'   => $user->id,
            'cate_id'   => $cate_id,
            'roundid'   => $roundid,
            'status'    => 0,
            'createtime'=> datetime(time()),
            'updatetime'=> datetime(time()),
        ];

        db('game_record')->insert($data);
        $this->success(__('请求成功'), ['roundid' => $roundid, 'money' => number_format($user->money, 2)]);
    }

    /**
     * 开始游戏
     */
    public function play()
    {
        $cate_id = $this->request->post('cate_id');

        $user = $this->auth->getUser();

        $order = $this->checkPlay($user, $cate_id);
        
        $where['user_id'] = $user->id;
        $where['cate_id'] = $cate_id;
        $where['status'] = '0';
        $game = db('game_record')->where($where)->field('id,roundid')->find();
        // dd($game);
        if (!$game) {
            $this->error(__('游戏不存在'));
        }

        $winItem = db('goods_cate')->where('cate_id', 1)->field('id,name,price,odds,is_win,image')->select();

        $arr = [];
        $goods = [];
        foreach($winItem as $v){
            $arr[$v['id']] = $v['odds'] * 10000;
            $goods[$v['id']] = $v;
        }
        
        // 抽中的格子
        $goods_id = Random::lottery($arr);
        // dd($goods);
        $goods = $goods[$goods_id];
        // $goods = $goods[1];
        $goods['image'] = $goods['image'] ? cdnurl($goods['image']) : '';

        // 获取九宫格
        $grid = $this->getGoodsGrid(1, $goods);

        $result = false;
        Db::startTrans();
        try{
            $user = User::lock(true)->find($user->id);

            $result = db('game_record')->where('id', $game['id'])->update([
                'status'        => 1, 
                'goods_cate_id' => $goods_id, 
                'win_amount'    => $goods['price'],
                'is_win'        => $goods['is_win'],
                'prizes'        => json_encode($goods),
                'updatetime'    => datetime(time()),
                'endtime'       => datetime(time()),
            ]);

            if(db('order')->where('id', $order['id'])->update(['status' => 2]) === false){
                $result = false;
            }
        
            if($goods['is_win'] > 0){
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
                    'transaction_id'    => $game['roundid'],
                ]) === false){
                    $result = false;
                }
            }
            
            if($result != false){
                Db::commit();
            }

        }catch(\Exception $e){
            echo $e->getMessage();
            Db::rollback();
            $this->error(__('请求失败'));
        }

        $win_amount = db('game_record')->where('cate_id', $cate_id)->where('user_id', $user->id)->sum('win_amount');
        $retval = [
            'win_amount' => $win_amount,
            'award_item' => $goods,
            'item'       => $grid,
            'money'      => number_format($user->money, 2),
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取九宫格
     */
    public function getGoodsGrid($cate_id, $goods)
    {
        $fields = "id,cate_id,goods_id,name,abbr,image,price,odds,is_win";
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