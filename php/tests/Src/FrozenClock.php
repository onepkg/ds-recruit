<?php

declare(strict_types=1);

namespace Tests\Src;

use DateTimeImmutable;
use Src\Clock;

/**
 * 测试替身：把「现在」固定在指定时刻。
 *
 * 问候语依赖运行时间，直接用真实时间做断言会随时间波动（flaky test）；
 * 冻结时钟让每个用例的输入完全确定，从而可以精确断言输出。
 */
final class FrozenClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
