<?php

namespace app\common\service\util;

use app\common\model\Cate;
use app\common\model\Goods;
use app\common\model\GoodsCate;
use think\Cache;

class Rtp
{

    /**
     * 根据RTP计算实际派奖金额
     * 
     * @param int $cate_id 游戏ID
     * @param float $price 游戏成本
     * @return float 实际派奖金额
     */
    public static function calculateWinAmount($cate_id, $price)
    {
        // 从Redis获取游戏配置
        $redisKey = "cate_rtp_config:{$cate_id}";
        $rtpConfig = Cache::store('redis')->get($redisKey);
        // $rtpConfig = null;
        if (!$rtpConfig) {
            // 从数据库加载配置
            $cate = Cate::where('id', $cate_id)->find();

            $goods = GoodsCate::where('cate_id', $cate_id)->field('id,cate_id,goods_id,name,abbr,image,price,odds,is_win')->select()->toarray();
            // dd($goods);
            // 计算理论RTP
            $theoreticalRtp = 0;
            foreach ($goods as $prize) {
                $theoreticalRtp += $prize['price'] * $prize['odds'];
            }

            // 计算实际RTP调整因子
            $adjustFactor = $cate['rtp'] / ($theoreticalRtp / $price);

            // 缓存配置
            $rtpConfig = [
                'theoretical_rtp'   => $theoreticalRtp,
                'adjust_factor'     => $adjustFactor,
                'prizes'            => $goods
            ];
            Cache::store('redis')->set($redisKey, json_encode($rtpConfig), 3600);
        } else {
            $rtpConfig = json_decode($rtpConfig, true);
        }

        // 根据概率抽取奖品
        $prize = self::selectPrize($rtpConfig['prizes']);
      
        // 应用RTP调整
        $winAmount = $prize['price'] * $rtpConfig['adjust_factor'];
        // dump($prize);
        // dump($winAmount);
        // dump($rtpConfig['adjust_factor']);
        // dd($prize);
        // 确保不会出现负值
        return max(0, round($winAmount, 2));
    }

    /**
     * 根据概率随机选择奖品
     */
    private static function selectPrize($prizes)
    {
        $rand = mt_rand() / mt_getrandmax(); // 0-1之间的随机数
        $cumulative = 0.0;

        foreach ($prizes as $prize) {
            $cumulative += $prize['odds'];
            if ($rand <= $cumulative) {
                return $prize;
            }
        }

        // 保底返回第一个奖品
        return $prizes[0];
    }
}
