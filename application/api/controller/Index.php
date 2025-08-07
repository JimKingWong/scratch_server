<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Admin;
use app\common\model\Mydata;
use app\common\model\Recharge;
use app\common\model\User;
use app\common\model\Withdraw;

/**
 * 首页接口
 * @ApiInternal
 * 
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function cs()
    {
        set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $today = date('Y-m-d');

        $users = User::whereTime('jointime', 'today')->where('is_test', 0)->select();

        // 注册人数
        $user_count = count($users);
        echo '注册人数: ' . $user_count. "\n";

        // 昨日用户id
        $user_ids = [];
        // 博主用户id
        $blogger_user_ids = [];

        // 注册且充值人数
        $register_recharge_users = 0;
        foreach($users as $user){
            if($user->is_first_recharge == 1){
                $register_recharge_users ++;
            }

            if($user->role == 1){
                $blogger_user_ids[] = $user->id;
            }

            $user_ids[] = $user->id;
        }
        echo '注册且充值人数: ' . $register_recharge_users. "\n";

        // 复冲人数
        $repeat_recharge_users = 0;
        // 复冲金额
        $repeat_recharge_money = 0;
        // 充值总金额
        $recharge_money = 0;
        // 博主充值金额
        $blogger_recharge_money = 0;
        
        $recharges = Recharge::whereTime('paytime', 'today')
            ->where('status', '1')
            ->field("user_id,count(id) count,sum(money) money")
            ->group('user_id')
            ->select();
        foreach($recharges as $recharge){
            if($recharge['count'] > 1){
                $repeat_recharge_users ++;
                $repeat_recharge_money += $recharge['money'];
            }

            if(in_array($recharge['user_id'], $blogger_user_ids)){
                $blogger_recharge_money += $recharge['money'];
            }
            $recharge_money += $recharge['money'];
        }

        // 充值人数
        $recharge_count = count($recharges);
        echo '复冲人数: ' . $repeat_recharge_users. "\n";
        echo '复冲金额: ' . $repeat_recharge_money. "\n";
        echo '充值人数: ' . $recharge_count. "\n";
        echo '充值总金额: ' . $recharge_money. "\n";

        // 提现金额
        $withdraw_money = 0;
        // 博主提现金额
        $blogger_withdraw_money = 0;
        $withdraws = Withdraw::whereTime('paytime', 'today')
            ->where('status', '1')
            ->where('is_virtual', 0)
            ->field("user_id,sum(money) money")
            ->group('user_id')
            ->select();

        foreach($withdraws as $withdraw){
            $withdraw_money += $withdraw['money'];
            if(in_array($withdraw['user_id'], $blogger_user_ids)){
                $blogger_withdraw_money += $withdraw['money'];
            }
        }

        // 客户提现金额
        $member_withdraw_money = $withdraw_money - $blogger_withdraw_money;

        echo '提现金额: ' . $withdraw_money. "\n";
        echo '博主提现金额: ' . $blogger_withdraw_money. "\n";
        echo '客户提现金额: ' . $member_withdraw_money. "\n";

        $rate_user = "0.00%";
        if($recharge_money != 0) $rate_user = bcdiv($member_withdraw_money, $recharge_money, 4) * 100 . "%";

        // 通道费用
        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');
        $channel_fee = $recharge_money * $recharge_channel_rate + $withdraw_money * $withdraw_channel_rate;
        echo '通道费用: ' . $channel_fee. "\n";


        $cate = db('cate')->column('id,name');

        $game_record = db('game_record')->whereTime('createtime', 'today')
            ->field('id,is_win,cate_id,win_amount,bet_amount')
            ->select();
        
        $records = [];
        $user_lost = 0;
        $bet_amount = 0;
        $win_amount = 0;
        foreach($game_record as $val){
            $records[$val['cate_id']]['name'] = $cate[$val['cate_id']];
            if(!isset($records[$val['cate_id']]['bet_amount'])){
                $records[$val['cate_id']]['bet_amount'] = 0;
            }else{
                $records[$val['cate_id']]['bet_amount'] += $val['bet_amount'];
            }
            
            if(!isset($records[$val['cate_id']]['win_amount'])){
                $records[$val['cate_id']]['win_amount'] = 0;
            }else{
                $records[$val['cate_id']]['win_amount'] += $val['win_amount'];
            } 

            $records[$val['cate_id']]['user_lost'] = $records[$val['cate_id']]['bet_amount'] - $records[$val['cate_id']]['win_amount'];
            $records[$val['cate_id']]['rtp'] = $records[$val['cate_id']]['bet_amount'] == 0 ? 0 : round($records[$val['cate_id']]['win_amount'] / $records[$val['cate_id']]['bet_amount'] * 100, 2);

            $bet_amount += $val['bet_amount'];
            $win_amount += $val['win_amount'];
            $user_lost += $val['bet_amount'] - $val['win_amount'];
        }

        foreach($records as $record){
            echo $record['name'] . '下注 ' . $record['bet_amount'] . ' 派彩 ' . $record['win_amount'] . ' 客损 ' . $record['user_lost'] . ' RTP: ' . $record['rtp'] . "%\n";
        }



        echo '客损: ' . $user_lost. "\n";

        // API费用
        // $game_api_fee = $user_lost * config('channel.game_api_fee');
        $game_api_fee = 0;
        // echo 'API费用: ' . $game_api_fee. "\n";

        // 今日盈利 = 充值总金额 - 提现金额 - 通道费用 - API费用
        $profit = $recharge_money - $withdraw_money - $channel_fee - $game_api_fee;
        echo '今日盈利: ' . $profit. "\n";

   
        
        // 今日活动奖励
        $acvivityArr = ['pg_bet_bonus', 'loss_bonus', 'first_recharge_bonus', 'admin_bonus'];
        $acvivity = db('user_reward_log')->whereTime('createtime', 'today')->whereIn('type', $acvivityArr)->where('status', 1)->select();
        $pgReward = 0;
        $lossRebate = 0;
        $firstRecharge = 0;
        // 博主工资金额(后台)
        $wd_bz_wage = 0;
        foreach($acvivity as $val){
            if($val['type'] == 'pg_bet_bonus'){
                $pgReward += $val['money'];
            }else if($val['type'] == 'loss_bonus'){
                $lossRebate += $val['money'];
            }else if($val['type'] == 'first_recharge_bonus'){
                $firstRecharge += $val['money'];
            }else if($val['type'] == 'admin_bonus'){
                $wd_bz_wage += $val['money'];
            }
        }

        // 博主工资金额(流水)
        $bz_commission = db('user_reward_log')->whereTime('createtime', 'today')->where('status', 1)->whereIn('type', ['direct_bonus', 'indirect_bonus'])->sum('money');

        $today = date("Y-m-d H:i:s") . "\n";

        $str = "==={$today}=== \n";
        $str .= "===彩票长台-数据报表=== \n";
        foreach($records as $record){
            $str .= $record['name'] . "下注 " . $record['bet_amount'] . " 派彩 " . $record['win_amount'] . " 客损 " . $record['user_lost'] . " RTP: " . $record['rtp'] . "%\n";
        }
        $str .= "注册人数: $user_count \n";
        $str .= "注册且充值人数: $register_recharge_users \n";
        $str .= "复充人数: $repeat_recharge_users \n";
        $str .= "复充金额: $repeat_recharge_money \n";
        $str .= "充值人数: $recharge_count \n";
        $str .= "充值金额: $recharge_money \n";
        $str .= "总流水: $bet_amount \n";
        $str .= "总派彩: $win_amount \n";
        $str .= "总客损: $user_lost \n";
        $str .= "提现金额: $withdraw_money \n";
        $str .= "博主工资金额(后台): $wd_bz_wage \n";
        $str .= "博主佣金发放(流水): $bz_commission \n";
        $str .= "博主充值金额: $blogger_recharge_money \n";
        $str .= "博主提现金额: $blogger_withdraw_money \n";
        $str .= "玩家提现金额: $member_withdraw_money ($rate_user) \n";
        
        $str .= "通道费用: $channel_fee \n";
        $str .= "今日盈利: $profit \n";
        echo $str; return;
    }

    /**
     * 首页
     *
     */
    public function index()
    {
         set_time_limit(0); // 取消执行时间限制
        ini_set('memory_limit', '256M'); // 调整内存限制

        $field = "id,username,role";
        $admins = Admin::where('role', '>', 2)->field($field)->select();

        $admin_ids = [];
        foreach($admins as $admin){
            $admin_ids[] = $admin->id;
        }

        array_push($admin_ids, 0);

        // 过去几天, 修改这个参数
        $day = 1;
        // 昨天的数据
        $starttime = date('Y-m-d 00:00:00', strtotime('-'. $day .' day'));
        $endtime = date('Y-m-d 23:59:59', strtotime('-'. $day .' day'));
        $where['paytime'] = ['between', [$starttime, $endtime]];
        
        $recharge = Recharge::where('paytime', 'between', [$starttime, $endtime])
            ->where('status', '1')
            ->whereIn('admin_id', $admin_ids)
            ->group('admin_id')
            ->column('sum(money)', 'admin_id');

        $withdraw = Withdraw::where('paytime', 'between', [$starttime, $endtime])
            ->where('status', '1')
            ->whereIn('admin_id', $admin_ids)
            ->group('admin_id')
            ->column('sum(money)', 'admin_id');

        // 博主工资
        $salary = db('user_reward_log')->where('createtime', 'between', [$starttime, $endtime])->where('status', 1)->where('type', 'admin_bonus')->group('admin_id')->column('sum(money)', 'admin_id');
        // $es = new Es();

        // 用作判断是否已插入
        $adminLogs = db('daybookadmin')->where('date', date('Y-m-d', strtotime('-' . $day . ' day')))->column('id', 'admin_id');

        $recharge_channel_rate = config('channel.recharge_channel_rate');
        $withdraw_channel_rate = config('channel.withdraw_channel_rate');

        $data = [];
        foreach($admin_ids as $key => $admin_id){
            if(!isset($adminLogs[$admin_id])){
                
                $api_fee = 0;

                $recharge_amount = $recharge[$admin_id] ?? 0;
                $withdraw_amount = $withdraw[$admin_id] ?? 0;
                $transfer_amount = $recharge_amount - $withdraw_amount;
                $channel_fee = $recharge_amount * $recharge_channel_rate + $withdraw_amount * $withdraw_channel_rate;
                $profit_and_loss = $recharge_amount - $withdraw_amount - $api_fee - $channel_fee;
                
                $data[] = [
                    'admin_id'              => $admin_id,
                    'salary'                => $salary[$admin_id] ?? 0,
                    'recharge_amount'       => $recharge[$admin_id] ?? 0,
                    'withdraw_amount'       => $withdraw[$admin_id] ?? 0,
                    'transfer_amount'       => $transfer_amount,
                    'api_amount'            => sprintf('%.2f', $api_fee),
                    'channel_fee'           => sprintf('%.2f', $channel_fee),
                    'profit_and_loss'       => sprintf('%.2f', $profit_and_loss),
                    'date'                  => date('Y-m-d', strtotime('-'.$day.' day')),
                    'createtime'            => date('Y-m-d H:i:s'),
                ];
            }
        }
        // dd($data);
        if(empty($data)){
            echo 'summaryAdminDaybook: 没有数据'. "\n"; return;
        }

        db('daybookadmin')->insertAll($data);
        echo "summaryAdminDaybook: 生成日结报表，日期：{" . date('Y-m-d', strtotime('-'.$day.' day')) . "}，处理记录数：" . count($data). "\n";
        $this->success(__('请求成功'));
    }

    /**
     * 部署第一步
     */
    private function startup()
    {
        // 创建es
        $service = new \app\common\service\util\Startup;
        // 创建es
        // $service::createEs();

        // 清理数据库数据
        $service::clearData();

    }

}
