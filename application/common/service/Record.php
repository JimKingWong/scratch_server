<?php

namespace app\common\service;

use app\common\model\Dictionary;
use app\common\model\MoneyLog;
use app\common\model\Recharge;
use app\common\model\RewardLog;
use app\common\model\Withdraw;

/**
 * 记录服务
 */
class Record extends Base
{
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 奖励记录
     */
    public function rewardLog()
    {
        $limit = (int)$this->request->get('limit', 10);
        $type = $this->request->get('type');
        $date = $this->request->get('date/d', '');
        $status = $this->request->get('status');

        // 今天 昨天, 过去7天, 过去15天, 过去30天
        if(!in_array($date, [0, 1, 7, 15, 30])) $date = ''; // 默认全部
        if(!in_array($status, [0, 1, 2])) $status = ''; // 默认全部

        $dictionary = Dictionary::where('type', 1)->field('title,name')->select();
        foreach($dictionary as $val){
            $val->title = __($val->title);
        }

        $where['user_id'] = $this->auth->id;

        // 奖励类型
        if($type != ''){
            $where['type'] = $type;
        }

        // 状态
        if($status != ''){
            $where['status'] = (string)$status;
        }

        if($date === 0){
            $starttime = date('Y-m-d');
            $where['createtime'] = ['>=', $starttime];
        }

        if($date > 0){
            $starttime = date('Y-m-d', strtotime('-' . $date . ' day'));
            $endtime = date('Y-m-d');
            $where['createtime'] = ['between', [$starttime, $endtime]];
        }
        // dd($where);

        $list = RewardLog::where($where)
            ->order('id desc')
            ->field('id,type,money,memo,createtime,status')
            ->select();
            // ->paginate([
            //     'list_rows' => $limit,
            //     'query'     => $this->request->param(),
            // ]);
        foreach($list as $val){
            $val->memo = __($val->memo);
            $val->createtime = date('Y-m-d H:i:s', $val->createtime);
        }

        // 总奖金
        $bonus = RewardLog::where($where)->sum('money');
        $retval = [
            'dictionary'    => $dictionary,
            'bonus'         => $bonus,
            'list'          => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 余额明细
     */
    public function moneyLog()
    {
        $limit = (int)$this->request->get('limit', 10);
        $type = $this->request->get('type');
        $date = $this->request->get('date/d', '');

        // 今天 昨天, 过去7天, 过去15天, 过去30天
        if(!in_array($date, [0, 1, 7, 15, 30])) $date = ''; // 默认全部

        $dictionary = Dictionary::field('title,name')->select();
        foreach($dictionary as $val){
            $val->title = __($val->title);
        }

        $where['user_id'] = $this->auth->id;

        // 奖励类型
        if($type != ''){
            $where['type'] = $type;
        }

        if($date === 0){
            $starttime = date('Y-m-d');
            $where['createtime'] = ['>=', $starttime];
        }

        if($date > 0){
            $starttime = date('Y-m-d', strtotime('-' . $date . ' day'));
            $endtime = date('Y-m-d');
            $where['createtime'] = ['between', [$starttime, $endtime]];
        }
        // dd($where);

        $list = MoneyLog::where($where)
            ->order('id desc')
            ->field('id,type,money,before,after,memo,createtime')
            ->select();
            // ->paginate([
            //     'list_rows' => $limit,
            //     'query'     => $this->request->param(),
            // ]);
        foreach($list as $val){
            $val->memo = __($val->memo);
            $val->money = $val->after - $val->before > 0 ? '+' . $val->money : '-' . $val->money;
            $val->createtime = date('Y-m-d H:i:s', $val->createtime);
        }

        // 总奖金
        $bonus = RewardLog::where($where)->sum('money');
        
        unset($where['type']);
        $total_recharge = Recharge::where($where)->sum('money');
        $total_withdraw = Withdraw::where($where)->sum('money');
        $retval = [
            'dictionary'        => $dictionary,
            'bonus'             => $bonus,
            'total_recharge'    => $total_recharge,
            'total_withdraw'    => $total_withdraw,
            'list'              => $list,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 余额详情
     */
    public function moneyDetail()
    {
        $id = $this->request->get('id/d', 0);
        $where['id'] = $id;
        $where['user_id'] = $this->auth->id;
        $detail = MoneyLog::where($where)
            ->field('id,type,money,before,after,memo,createtime')
            ->find();

        $detail->memo = __($detail->memo);

        if(!$detail){
            $this->error(__('无效参数'));
        }

        $retval = [
            'detail' => $detail,
        ];
        $this->success(__('请求成功'), $retval);
    }

}