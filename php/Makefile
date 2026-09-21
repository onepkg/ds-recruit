# Docker Compose 兼容层：
# 优先使用 v2 插件（docker compose），没有时回退到 v1 独立命令（docker-compose）。
# 改进点：原来写死 `docker-compose`，而当前环境只安装了 v2 插件，
# 导致 `make dev-tests` 在第一行命令就失败。可用 `make COMPOSE="..."` 覆盖。
COMPOSE ?= $(shell if command -v docker-compose >/dev/null 2>&1; then echo docker-compose; else echo docker compose; fi)

.PHONY: all tests vendor dev-build dev-up dev-attach dev-down dev-clean dev-tests

all: tests

# 宿主机本地运行测试（需要本机 PHP >= 8.3 和 composer；适合改代码时的快速反馈）
vendor: composer.json composer.lock
	composer install --no-interaction --no-progress

tests: vendor
	vendor/bin/phpunit --configuration phpunit.xml --testdox

# 容器开发环境（可选，用于手工排查）
## 构建镜像
dev-build:
	$(COMPOSE) build

## 启动常驻开发容器
dev-up: dev-build
	$(COMPOSE) up -d --remove-orphans

## 连接进入开发环境
dev-attach: dev-up
	$(COMPOSE) exec recruit sh

## 关闭开发环境
dev-down:
	$(COMPOSE) down --remove-orphans

## 清除开发环境
dev-clean: dev-down
	$(COMPOSE) rm -fv
	docker container prune -f
	docker image prune -f

# 最终测试用命令。
# 改进点：
#   1. 先构建镜像，把当前工作区的代码打进镜像，再用一次性容器跑测试，跑完自动删除；
#   2. 不再依赖常驻容器（原先要先 dev-up，容器没起来时 exec 会直接失败）；
#   3. 不需要 TTY，在 CI 等无终端环境下也能稳定运行（v1 的 exec 在无 TTY 时会报错）。
dev-tests: dev-build
	$(COMPOSE) run --rm recruit make tests
