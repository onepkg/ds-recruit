<?php

declare(strict_types=1);

namespace Src;

use DateTimeImmutable;

/**
 * 时间来源抽象：「现在几点」由外部提供，而不是在业务代码里直接调用 date()/time()。
 *
 * 好处是 MyGreeter 不再强依赖真实时间，测试可以注入冻结时钟，
 * 从而确定性地覆盖 6AM/12PM/6PM 这些边界（否则测试结果会随执行时刻变化）。
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
