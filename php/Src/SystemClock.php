<?php

declare(strict_types=1);

namespace Src;

use DateTimeImmutable;

/**
 * 系统时钟：生产环境使用，返回服务器当前时间。
 *
 * 注意时区取自 PHP 的 date.timezone 配置（容器默认 UTC），
 * 部署时需要确认它与业务期望的时区一致，否则同一时刻可能返回不同问候语。
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
