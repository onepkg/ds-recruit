<?php

declare(strict_types=1);

namespace Src;

/**
 * 根据当前运行时间返回问候语。
 *
 * 时间来源通过构造函数注入（默认使用系统时钟），
 * 这样生产代码保持简单，同时测试可以注入冻结时钟做确定性断言。
 */
final class MyGreeter
{
    /** 早晨起点：6AM（含） */
    private const MORNING_START_HOUR = 6;

    /**
     * 下午起点：中午 12:00（含）。
     *
     * README 原文写作「12AM」，但 12AM 按惯例指午夜 0 点，与「6AM 至 12AM 为早晨」的
     * 上一句自相矛盾；结合上下文这里应是 12PM（正午）。
     */
    private const AFTERNOON_START_HOUR = 12;

    /** 晚上起点：6PM（含） */
    private const EVENING_START_HOUR = 18;

    public function __construct(private readonly Clock $clock = new SystemClock())
    {
    }

    /**
     * 返回当前时间对应的问候语。
     *
     * 三个区间按「左闭右开」划分，保证任意时刻有且只有一个归属：
     *   [06:00, 12:00)         Good morning
     *   [12:00, 18:00)         Good afternoon
     *   [18:00, 次日 06:00)    Good evening
     */
    public function greeting(): string
    {
        // G：24 小时制且无前导零，便于直接做数值比较
        $hour = (int) $this->clock->now()->format('G');

        return match (true) {
            $hour >= self::MORNING_START_HOUR && $hour < self::AFTERNOON_START_HOUR => 'Good morning',
            $hour >= self::AFTERNOON_START_HOUR && $hour < self::EVENING_START_HOUR => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
