<?php

namespace app\common\service\util;

use app\common\model\GoodsCate;

class Scratch
{

    /**
     * 生成刮刮卡结果
     * 
     * @param int $cate_id 游戏ID
     * @param float $price 游戏成本
     * @return array [中奖金额, 刮开结果数组]
     */
    public static function generate($cate_id, $price)
    {
        // 计算实际中奖金额
        $winAmount = Rtp::calculateWinAmount($cate_id, $price);
        // dd($winAmount);
        // 获取游戏配置
        $prizes = GoodsCate::where('cate_id', $cate_id)
            ->where('is_win', 1)
            ->select()->toarray();

        // 生成3x3矩阵
        $result = [];
        $winSymbol = null;

        // 如果有中奖
        if ($winAmount > 0) {
            // 随机选择一种中奖符号
            $winPrize = $prizes[array_rand($prizes)];
            $winSymbol = $winPrize['name'];

            // 随机生成3个相同符号的位置
            $positions = self::generateWinPositions();

            // 填充中奖符号
            foreach ($positions as $pos) {
                $result[$pos] = $winSymbol;
            }

            // 填充其他符号
            $otherPrizes = GoodsCate::where('cate_id', $cate_id)
                ->where('name', '<>', $winSymbol)
                ->select();

            for ($i = 0; $i < 9; $i++) {
                if (!isset($result[$i])) {
                    $randomPrize = $otherPrizes[array_rand($otherPrizes)];
                    $result[$i] = $randomPrize['name'];
                }
            }
        } else {
            // 未中奖，全部填充非中奖项
            $nonWinPrizes = GoodsCate::where('cate_id', $cate_id)
                ->where('is_win', 0)
                ->select();
            // dd($nonWinPrizes);
            for ($i = 0; $i < 9; $i++) {
                $randomPrize = $nonWinPrizes[array_rand($nonWinPrizes->toarray())];
                $result[$i] = $randomPrize['name'];
            }
        }

        // 打乱顺序（保持中奖位置不变）
        $values = array_values($result);
        $keys = array_keys($result);
        shuffle($values);

        return [
            'win_amount' => $winAmount,
            'prizes'     => array_combine($keys, $values)
        ];
    }

    /**
     * 生成3个不重复的随机位置
     */
    private static function generateWinPositions()
    {
        $positions = range(0, 8);
        shuffle($positions);
        return array_slice($positions, 0, 3);
    }
}
