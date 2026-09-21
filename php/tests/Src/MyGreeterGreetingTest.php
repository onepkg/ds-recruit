<?php

declare(strict_types=1);

namespace Tests\Src;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\MyGreeter;

/**
 * MyGreeter 时间分支的补充测试。
 *
 * 官方提供的 MyGreeterTest 只断言「返回了非空字符串」，无法覆盖三个时间段的分支；
 * 这里通过注入冻结时钟补上确定性断言，并覆盖最容易写错的边界点。
 *
 * 边界约定（README 中的三个区间按左闭右开处理）：
 *   06:00 <= t < 12:00         -> Good morning
 *   12:00 <= t < 18:00         -> Good afternoon
 *   18:00 <= t < 次日 06:00    -> Good evening
 */
#[CoversClass(MyGreeter::class)]
final class MyGreeterGreetingTest extends TestCase
{
    /**
     * 覆盖三个区间的中间值、三个边界点，以及跨天（午夜前后）的场景。
     *
     * @return iterable<string, array{string, string}>
     */
    public static function greetingCases(): iterable
    {
        yield '区间起点边界 06:00' => ['06:00:00', 'Good morning'];
        yield '上午 09:30' => ['09:30:00', 'Good morning'];
        yield '上午最后一秒 11:59:59' => ['11:59:59', 'Good morning'];
        yield '区间起点边界 12:00' => ['12:00:00', 'Good afternoon'];
        yield '下午 15:00' => ['15:00:00', 'Good afternoon'];
        yield '下午最后一秒 17:59:59' => ['17:59:59', 'Good afternoon'];
        yield '区间起点边界 18:00' => ['18:00:00', 'Good evening'];
        yield '深夜 23:59:59' => ['23:59:59', 'Good evening'];
        yield '跨天后的午夜 00:00:00' => ['00:00:00', 'Good evening'];
        yield '次日清晨边界前一秒 05:59:59' => ['05:59:59', 'Good evening'];
    }

    #[DataProvider('greetingCases')]
    public function test_greeting_depends_on_current_time(string $time, string $expected): void
    {
        $greeter = new MyGreeter(new FrozenClock(new DateTimeImmutable('2026-09-21 ' . $time)));

        self::assertSame($expected, $greeter->greeting());
    }

    public function test_greeting_returns_string(): void
    {
        $greeter = new MyGreeter(new FrozenClock(new DateTimeImmutable('2026-09-21 09:00:00')));

        self::assertIsString($greeter->greeting());
    }

    /**
     * 时钟参数可选：不注入时应使用系统时间。
     * 这里断言结果属于三种合法问候语之一，而不是断言具体值，避免测试依赖执行时刻。
     */
    public function test_default_constructor_falls_back_to_system_clock(): void
    {
        $greeter = new MyGreeter();

        self::assertContains(
            $greeter->greeting(),
            ['Good morning', 'Good afternoon', 'Good evening']
        );
    }
}
