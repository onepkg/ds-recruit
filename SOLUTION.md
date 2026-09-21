# 解题说明（PHP 实现）

对应 README 的「动手」与「思考」两部分，所有代码改动都在 `php/` 目录下。

> 说明：官方提供的单元测试 `php/tests/Src/MyGreeterTest.php` **未做任何修改**，
> 它仍然原样参与测试；我新增的测试放在单独的文件里。

---

## 一、动手

### 1.1 文件清单

| 文件 | 类型 | 说明 |
| --- | --- | --- |
| `php/Src/MyGreeter.php` | 新增 | 题目要求实现的类 |
| `php/Src/Clock.php` | 新增 | "当前时间"来源接口，把时间依赖从业务逻辑中解耦 |
| `php/Src/SystemClock.php` | 新增 | 生产环境用的系统时钟 |
| `php/tests/Src/MyGreeterGreetingTest.php` | 新增 | 三个时间段 + 边界 + 跨天的确定性测试 |
| `php/tests/Src/FrozenClock.php` | 新增 | 测试替身：把"现在"冻结在某个时刻 |
| `php/phpunit.xml` | 修改 | 补 bootstrap / testsuite / cacheDirectory |
| `php/Dockerfile` | 修改 | 装 make、git、composer；构建期装依赖；把源码 COPY 进镜像 |
| `php/docker-compose.yml` | 修改 | 去掉不可靠的目录挂载；显式常驻命令 |
| `php/Makefile` | 修改 | 兼容 compose v2/v1；`dev-tests` 改为一次性容器执行 |
| `php/.dockerignore` | 新增 | 排除 vendor/.git 等，避免污染构建上下文 |
| `php/.gitignore` | 修改 | 不再忽略 `composer.lock` |
| `php/composer.json` | 修改 | 声明 PHP 版本约束，新增 `composer test` 脚本 |

### 1.2 实现要点

**时间区间约定（左闭右开）**

| 运行时间 | 返回 |
| --- | --- |
| `[06:00, 12:00)` | `Good morning` |
| `[12:00, 18:00)` | `Good afternoon` |
| `[18:00, 次日 06:00)` | `Good evening` |

- 采用左闭右开可以保证任意时刻有且只有一个归属，不会出现"12:00 到底算上午还是下午"的歧义；
  边界点归属为：`06:00 → morning`、`12:00 → afternoon`、`18:00 → evening`。
- README 里把中午写成了 `12AM`。按通行惯例 `12AM` 指午夜 0 点，这会和"6AM 至 12AM 为早晨"
  的前一句自相矛盾；结合上下文判断出题人想表达的是 `12PM`（正午），实现时按正午处理，并已在
  代码注释中标注。

**为什么注入 `Clock` 而不是直接调用 `date()`**

- `greeting()` 的结果依赖"运行时刻"这个外部状态，直接在类里读系统时间，测试就只能等
  真实时间走到某个区间才能断言，结果不可重复（这类测试就是典型的 flaky test）。
- 把时间抽成 `Clock` 接口后，构造函数保留默认值 `new SystemClock()`，
  所以 `new MyGreeter()` 这种最简用法依然成立（官方测试就是这么用的），
  而测试可以注入冻结时钟，对三个区间和所有边界点做精确断言。

**时区提醒**：`SystemClock` 使用 PHP 的 `date.timezone` 配置，而 `php:8.3-cli-alpine`
默认是 UTC。如果业务期望的是本地时间（如 JST），需要显式配置时区，否则同一时刻在不同
部署环境下可能返回不同问候语。这一点已写进 `SystemClock` 的注释。

### 1.3 运行方式与结果

```console
$ make dev-tests
...
composer install --no-interaction --no-progress
Installing dependencies from lock file (including require-dev)
Verifying lock file contents can be installed on current platform.
Warning: The lock file is not up to date with the latest changes in composer.json. You may be getting outdated dependencies. It is recommended that you run `composer update` or `composer update <package name>`.
Nothing to install, update or remove
Generating autoload files
25 packages you are using are looking for funding.
Use the `composer fund` command to find out more!
vendor/bin/phpunit --configuration phpunit.xml --testdox
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /srv/phpunit.xml

..............                                                    14 / 14 (100%)

Time: 00:00.008, Memory: 8.00 MB

My Greeter
 ✔ Init
 ✔ Greeting

My Greeter Greeting (Tests\Src\MyGreeterGreeting)
 ✔ Greeting depends on current time with 区间起点边界·06:00
 ✔ Greeting depends on current time with 上午·09:30
 ✔ Greeting depends on current time with 上午最后一秒·11:59:59
 ✔ Greeting depends on current time with 区间起点边界·12:00
 ✔ Greeting depends on current time with 下午·15:00
 ✔ Greeting depends on current time with 下午最后一秒·17:59:59
 ✔ Greeting depends on current time with 区间起点边界·18:00
 ✔ Greeting depends on current time with 深夜·23:59:59
 ✔ Greeting depends on current time with 跨天后的午夜·00:00:00
 ✔ Greeting depends on current time with 次日清晨边界前一秒·05:59:59
 ✔ Greeting returns string
 ✔ Default constructor falls back to system clock

OK (14 tests, 14 assertions)
```

---

## 二、容器环境改进点

改动的出发点是"让 `make dev-tests` 真正能在当前环境跑起来，并且结果可信"。逐条说明如下。

### 改进点 1：宿主机没有 `docker-compose` 命令

- **问题**：Makefile 里写死了 `docker-compose`，而当前环境只装了 Compose v2 插件
  （`docker compose`），`make dev-tests` 第一条命令就失败。
- **改动**：Makefile 里增加 `COMPOSE` 变量，优先用 `docker-compose`（v1），
  没有则回退到 `docker compose`（v2），也可用 `make COMPOSE=...` 覆盖。
- **意图**：v1 与 v2 的命令名不同是常见的环境差异，构建脚本不应该假定其中一种。

### 改进点 2：镜像里没有 `make`

- **问题**：`php:8.3-cli-alpine` 不含 make，容器内执行 `make tests` 会报
  `exec: "make": executable file not found in $PATH`。
- **改动**：Dockerfile 中 `apk add --no-cache make git`。
- **意图**：`make dev-tests` 的实现方式是在容器内复用同一份 Makefile，
  镜像必须提供 make；顺带补上 git（composer 从源码安装依赖时需要）。

### 改进点 3：镜像里没有 composer，每次都要联网下载 installer

- **问题**：原 Makefile 用 `curl ... | php` 现场下载 composer 安装器，
  依赖外网、无校验、每次新容器都要重来一遍。
- **改动**：Dockerfile 里 `COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer`，
  并在构建阶段执行 `composer install`（先拷 `composer.json`/`composer.lock`，再拷源码，充分利用层缓存）。
- **意图**：依赖在构建期一次性装好，测试运行时不再需要网络，启动也更快。

### 改进点 4：常驻容器靠 `tty: true` 隐式保活，测试又依赖 `exec` + TTY

- **问题**：常驻容器仅靠 `tty: true` + 基础镜像默认的 `php -a` 维持存活，行为隐式；
  `dev-tests` 依赖先 `up -d` 再 `exec`，在 CI 等没有 TTY 的环境下 v1 的 `exec` 会直接报错。
- **改动**：
  - 服务改为显式的 `command: ["sleep", "infinity"]`；
  - `dev-tests` 改为 `$(COMPOSE) run --rm recruit make tests`：先构建镜像，
    再用一次性容器跑测试，跑完自动清理，不需要常驻容器，也不需要 TTY。
- **意图**：测试命令应该是自包含、可重复、可在无终端环境运行的。

### 改进点 5：`--compatibility` 是 v1 时代的兼容参数

- **问题**：`deploy.resources.limits` 在 v1 下需要 `--compatibility` 才会被翻译成容器限制，
  该参数在 v2 下已废弃。
- **改动**：去掉 `--compatibility`，保留 512M 内存限制。
- **意图**：Compose v2 会直接应用 `deploy.resources.limits`（已实测容器 `Memory=536870912`），
  没必要保留一个会让 v2 打印废弃告警的参数。

### 改进点 6：`phpunit.xml` 缺 bootstrap 与 testsuite

- **问题**：原配置只有几个 `display*` 选项，测试靠命令行参数指定目录才能跑，
  自动加载也依赖 PHPUnit 二进制的隐式行为，配置不完整。
- **改动**：补 `bootstrap="vendor/autoload.php"`、`<testsuites>`、`cacheDirectory=".phpunit.cache"`。
- **意图**：`phpunit` 可以零参数直接运行，CI 或 IDE 接入时不必再复刻命令行参数；
  缓存目录写进被忽略的路径，不污染工作区。

### 改进点 7：`composer.lock` 被 `.gitignore` 忽略

- **问题**：锁定文件不入库，本地与容器、不同时间构建出的依赖版本可能不一致，
  "在我机器上是好的"这类问题难以排查。
- **改动**：从 `.gitignore` 中移除 `composer.lock`（文件本身已生成，可随代码一起提交）。
- **意图**：可复现构建。对应用型项目来说 lock 文件应当纳入版本控制。

### 改进点 8：缺少 `.dockerignore`

- **问题**：构建上下文包含 `vendor/`、`.git/` 等目录，既拖慢构建，
  又可能把宿主机上的依赖覆盖进镜像（宿主机 PHP 版本与镜像不一致时尤其危险）。
- **改动**：新增 `.dockerignore` 排除 `vendor`、缓存、`.git` 等。
- **意图**：镜像内的 `vendor/` 只来自镜像内的 `composer install`，来源单一且确定。

---

## 三、思考

### 问题 1：我们准备的单元测试类（MyGreeterTest）是否存在问题？

**是。**

### 问题 2：有哪些问题？如何改善？

按严重程度排序，前两条是我认为必须修的。

#### 1. 断言过弱，实际上没有验证任何业务逻辑

`test_greeting` 只断言 `strlen($greeter->greeting()) > 0`。
任何返回非空字符串的实现都能通过——包括 `return 'Hello';` 这种完全忽略时间的实现。
测试对题目要求的三个时间段分支零覆盖，无法回答"6 点、12 点、18 点的行为是否正确"。

**改善**：用 `assertSame` 断言具体字符串，并用数据提供器覆盖三个区间：

```php
/** @return iterable<string, array{string, string}> */
public static function greetingCases(): iterable
{
    yield '06:00 上午起点'   => ['06:00:00', 'Good morning'];
    yield '11:59:59 上午末尾' => ['11:59:59', 'Good morning'];
    yield '12:00 下午起点'   => ['12:00:00', 'Good afternoon'];
    yield '17:59:59 下午末尾' => ['17:59:59', 'Good afternoon'];
    yield '18:00 晚上起点'   => ['18:00:00', 'Good evening'];
    yield '05:59:59 次日凌晨' => ['05:59:59', 'Good evening'];
}

#[DataProvider('greetingCases')]
public function test_greeting(string $time, string $expected): void
{
    self::assertSame($expected, (new MyGreeter(new FrozenClock(new DateTimeImmutable("2026-01-01 $time"))))->greeting());
}
```

#### 2. 测试结果依赖真实系统时间，不可重复

即使按第 1 条补上断言，只要被测对象直接读系统时间，测试结果就会随执行时刻变化：
同一份代码上午跑是绿的，晚上跑就红了。这类测试通常会被 CI 定时任务随机触发，
排查成本很高，最后往往被改成"跳过"或"重试"，失去意义。

**改善**：给被测类留出时间注入点（构造函数参数、`Clock` 接口、或 `psr/clock`），
测试注入冻结时间。若不允许修改被测类的设计，退而求其次的做法是接受三种结果之一
（我在新增的 `test_default_constructor_falls_back_to_system_clock` 里就是这么写的），
但这只能验证"返回值合法"，仍然无法验证区间划分是否正确。

#### 3. 缺少边界值与跨天用例

最容易写错的就是边界：6:00 属于早晨还是晚上、12:00 归上午还是下午、18:00 归下午还是晚上。
另外"6PM 至第二天 6AM"是跨天区间，`00:00`、`23:59`、`05:59` 这些点必须覆盖。
现在的测试对边界没有任何约束，等于没有定义清楚行为。

顺带指出：README 中"6AM 至 12AM"、"12AM 至 6PM"的表述本身就是有歧义的，
`12AM` 按惯例指午夜。需要出题方明确写成 `12PM`，否则实现者与出题人对边界的理解可能不一致。

#### 4. 测试类不符合 PSR-4 自动加载约定

`composer.json` 里声明了 `Tests\` → `tests/`，但 `tests/Src/MyGreeterTest.php` 中的类
位于全局命名空间。目前 PHPUnit 通过直接加载文件仍然能跑，但这类文件无法被
`Tests\Src\MyGreeterTest` 正常自动加载，重构或按类名定位测试时会出问题。

**改善**：加 `namespace Tests\Src;`，让类名、文件名与目录结构对齐
（`tests/Src/MyGreeterTest.php` ↔ `Tests\Src\MyGreeterTest`）。

#### 5. 测试方法缺少返回类型声明

`test_init()`、`test_greeting()` 都没有 `: void`，PHPUnit 11 会给出弃用提示，
未来版本可能直接报错。

**改善**：统一补上 `: void`。

#### 6. 细节问题

- `setUp()` 每个测试方法都重新实例化一次 `MyGreeter`，对无状态的被测对象属于多余开销；
  更重要的是，这里的实例没有任何时间注入入口，见第 2 条。
- 方法名风格不统一（`test_init` 用下划线，类名却用大驼峰），建议统一为 `testInit` 或
  使用 `#[Test]` 属性（PHPUnit 的惯例）。
- 没有对返回类型本身的断言，建议补一条 `assertIsString`，把接口契约固定下来。
- 可以进一步补 `#[CoversClass(MyGreeter::class)]`，配合 `--coverage` 约束覆盖范围，
  避免无关代码被算进覆盖率。

**我没有直接修改这份测试的原因**：它是验收用的"考题"，改它等于同时改考卷和答案；
上述问题已经用新增测试（`MyGreeterGreetingTest`）的方式覆盖，并在本文档中单独说明。

---

## 附：如何验证

```console
$ cd php
$ make dev-tests     # 构建镜像 + 容器内跑测试（README 要求）
$ make tests         # 宿主机跑测试（需要本机 PHP >= 8.3、composer）
$ make dev-up        # 需要手工排查时启动常驻容器
$ make dev-attach    # 进入容器
$ make dev-down      # 关闭
```
