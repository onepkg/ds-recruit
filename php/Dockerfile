# 基础镜像的 PHP 版本与 composer.json 中 config.platform.php 保持一致（8.3）
FROM php:8.3-cli-alpine

# 改进点 1：安装 make。
# 原镜像里没有 make，而 `make dev-tests` 需要在容器内执行 `make tests`，
# 实际运行时会报 "exec: make: executable file not found in $PATH"。
# 同时补上 git，供 composer 从源码安装依赖时使用（dist 包解压所需的 unzip 基础镜像已自带）。
RUN apk add --no-cache make git

# 改进点 2：直接复用官方 composer 镜像里的 composer。
# 原 Makefile 每次都要联网下载 installer（约 3MB）且没有校验，构建慢且依赖外网；
# 复制现成的二进制更快，也不需要在镜像里保留 curl 流程。
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /srv

# 改进点 3：先只拷贝依赖描述文件并安装依赖，利用 Docker 层缓存。
# 只要 composer.json / composer.lock 没变，修改源码就不会触发依赖重新下载。
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist

CMD ["php", "-a"]
