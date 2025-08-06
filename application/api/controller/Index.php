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
        $list = db('game_record a')->join('cate b', 'a.cate_id=b.id')->group('a.user_id')->field('a.user_id,sum(a.win_amount) win_amount,sum(b.price) bet_amount')->select();
        dd($list);
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
