#!/usr/bin/env bash
# deploy.sh - 卡密验证 SaaS 平台一键部署脚本
# 用法:
#   ./deploy.sh                 # 开发环境一键部署 (init + up)
#   ./deploy.sh --prod          # 生产环境一键部署
#   ./deploy.sh doctor          # 环境诊断
#   ./deploy.sh init [--prod]   # 仅初始化配置
#   ./deploy.sh up [--prod]     # 仅启动服务
#   ./deploy.sh down [--prod]   # 停止服务
#   ./deploy.sh status          # 查看状态
#   ./deploy.sh logs [service]  # 查看日志
#   ./deploy.sh backup          # 数据库备份
#   ./deploy.sh reset --yes     # 危险: 清空数据卷
#   ./deploy.sh enable-apk-inject   # 启用 APK 注入功能
#   ./deploy.sh install-gvisor  # 安装 gVisor (Debian/Ubuntu)

set -euo pipefail

# ==================== 全局变量 ====================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

PROD_MODE=false
ASSUME_YES=false
FORCE=false
DOMAIN=""
BRANCH=""
# 端口映射变化时由 handle_port_conflicts 设置为 true, start_services 据此追加 --force-recreate
FORCE_RECREATE=false

# 端口配置
DEV_PORTS=(8000 8080 3306 6379 9000 9001 8081)
PROD_PORTS=(80 443 3306 6379 9000 9001 8081)

# ==================== 日志函数 ====================
log_info() {
    printf "\033[32m[%s] [INFO] %s\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

log_warn() {
    printf "\033[33m[%s] [WARN] %s\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

log_error() {
    printf "\033[31m[%s] [ERROR] %s\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >&2
}

log_step() {
    printf "\n\033[36m[%s] === %s ===\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

# 失败即退出
die() {
    log_error "$*"
    exit 1
}

# ==================== 工具函数 ====================

# 检测 docker compose 命令 (v2 优先, v1 兜底)
detect_compose() {
    if docker compose version >/dev/null 2>&1; then
        echo "docker compose"
    elif command -v docker-compose >/dev/null 2>&1; then
        echo "docker-compose"
    else
        echo ""
        return 1
    fi
}

# 获取 compose 命令前缀 (含 prod 文件参数 + override 文件)
# 注意: compose v2 在使用多个 -f 时不会自动加载 docker-compose.override.yml,
# 必须显式 -f 指定, 否则 handle_port_conflicts 生成的 override 在 prod 模式下被忽略
get_compose_cmd() {
    local base
    base="$(detect_compose)" || die "未检测到 Docker Compose, 请先安装 (https://docs.docker.com/compose/install/)"
    local files="-f docker-compose.yml"
    if $PROD_MODE; then
        files="$files -f docker-compose.prod.yml"
    fi
    if [[ -f docker-compose.override.yml ]]; then
        files="$files -f docker-compose.override.yml"
        # 输出到 stderr: 本函数返回值会被 $(...) 捕获, 日志不能污染 stdout
        log_info "已加载 docker-compose.override.yml" >&2
    fi
    echo "$base $files"
}

# 检查当前用户是否有 docker 权限
ensure_root_or_docker_group() {
    if [[ $EUID -eq 0 ]]; then
        return 0
    fi
    if groups | grep -qw docker; then
        return 0
    fi
    # macOS 通常不需要 docker 组
    if [[ "$(uname)" == "Darwin" ]]; then
        return 0
    fi
    log_warn "当前用户不在 docker 组且非 root, 后续命令可能失败"
    log_warn "解决: sudo usermod -aG docker \$USER && newgrp docker"
    return 1
}

# 生成随机十六进制 (长度 = $1 字节, 输出 2*$1 字符)
# 优先用 openssl, 不可用时回退到 /dev/urandom
random_hex() {
    local len="${1:-16}"
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex "$len"
    elif [[ -r /dev/urandom ]]; then
        # 回退: 从 /dev/urandom 读取 len 字节, 转为 hex (2*len 字符)
        head -c "$len" /dev/urandom | od -An -tx1 | tr -d ' \n' | head -c $((len*2))
    else
        die "无法生成随机数: openssl 与 /dev/urandom 均不可用"
    fi
}

# 检查端口是否被占用, 返回 0=空闲 1=占用
# 用 awk 精确匹配 ss 第4列 (Local Address:Port) 末尾, 避免 :3306 误匹配 :33060
# 同时支持 IPv4 (0.0.0.0:3306) 和 IPv6 ([::]:3306 / :::3306) 监听
port_free() {
    local port="$1"
    if command -v ss >/dev/null 2>&1; then
        ss -tlnH 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${port}\$" && return 1 || return 0
    elif command -v lsof >/dev/null 2>&1; then
        lsof -iTCP:"${port}" -sTCP:LISTEN -P -n >/dev/null 2>&1 && return 1 || return 0
    else
        # 兜底用 /proc/net/tcp 和 tcp6 (Linux)
        local hex_port
        hex_port=$(printf "%04X" "${port}")
        grep -q ":${hex_port} " /proc/net/tcp /proc/net/tcp6 2>/dev/null && return 1 || return 0
    fi
}

# 从 .env 读取指定键的值
# 返回 0=成功(输出值), 1=失败(文件不存在/键不存在/值为空)
env_get() {
    local key="$1"
    local file="${2:-.env}"
    [[ -f "$file" ]] || return 1
    local val
    val=$(grep -E "^${key}=" "$file" 2>/dev/null | tail -n1 | cut -d'=' -f2- | tr -d '"') || return 1
    [[ -n "$val" ]] || return 1
    printf '%s' "$val"
}

# ==================== 参数解析 ====================
parse_global_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --prod)
                PROD_MODE=true
                shift
                ;;
            --yes|-y)
                ASSUME_YES=true
                shift
                ;;
            --force)
                FORCE=true
                shift
                ;;
            --domain=*)
                DOMAIN="${1#*=}"
                shift
                ;;
            --branch=*)
                BRANCH="${1#*=}"
                shift
                ;;
            --help|-h)
                sed -n '2,15p' "$0"
                exit 0
                ;;
            --*)
                log_warn "未知参数: $1"
                shift
                ;;
            *)
                break
                ;;
        esac
    done
    return $#
}

# ==================== doctor 子命令 (FR-1) ====================
cmd_doctor() {
    log_step "环境诊断开始"
    local has_error=false

    # 1. Docker 版本检查
    log_step "1. 检查 Docker"
    if ! command -v docker >/dev/null 2>&1; then
        log_error "Docker 未安装 (要求 ≥ 20.10)"
        log_error "安装: curl -fsSL https://get.docker.com | sh"
        has_error=true
    else
        local docker_version
        docker_version=$(docker version --format '{{.Server.Version}}' 2>/dev/null | head -n1 || echo "")
        if [[ -z "$docker_version" ]]; then
            log_warn "Docker daemon 未运行或当前用户无权访问"
            has_error=true
        else
            local major
            major=$(echo "$docker_version" | cut -d. -f1)
            if [[ "$major" -ge 20 ]]; then
                log_info "Docker 版本: $docker_version ✓"
            else
                log_error "Docker 版本过低: $docker_version (要求 ≥ 20.10)"
                has_error=true
            fi
        fi
    fi

    # 2. Docker Compose 检查
    log_step "2. 检查 Docker Compose"
    local compose_cmd
    compose_cmd="$(detect_compose 2>/dev/null || true)"
    if [[ -z "$compose_cmd" ]]; then
        log_error "Docker Compose 未安装 (要求 v2 或 v1)"
        has_error=true
    else
        local compose_version
        compose_version=$($compose_cmd version --short 2>/dev/null || echo "unknown")
        log_info "Docker Compose: $compose_cmd (版本 $compose_version) ✓"
    fi

    # 3. docker 权限检查
    log_step "3. 检查 docker 权限"
    if ensure_root_or_docker_group; then
        log_info "docker 权限正常 ✓"
    fi

    # 4. 端口占用检查
    log_step "4. 检查端口占用"
    local ports_to_check
    if $PROD_MODE; then
        ports_to_check=("${PROD_PORTS[@]}")
    else
        ports_to_check=("${DEV_PORTS[@]}")
    fi
    for port in "${ports_to_check[@]}"; do
        if ! port_free "$port"; then
            log_warn "端口 $port 已被占用 (可能为已运行的服务)"
        else
            log_info "端口 $port 空闲 ✓"
        fi
    done

    # 5. 磁盘空间检查 (≥ 5GB)
    log_step "5. 检查磁盘空间"
    local avail_kb
    avail_kb=$(df -k "$SCRIPT_DIR" | awk 'NR==2 {print $4}')
    local avail_gb=$((avail_kb / 1024 / 1024))
    if [[ "$avail_gb" -ge 5 ]]; then
        log_info "可用磁盘空间: ${avail_gb}GB ✓"
    else
        log_error "可用磁盘空间不足: ${avail_gb}GB (要求 ≥ 5GB)"
        has_error=true
    fi

    # 6. openssl 检查 (用于生成随机密钥)
    log_step "6. 检查 openssl"
    if command -v openssl >/dev/null 2>&1; then
        log_info "openssl: $(openssl version 2>/dev/null || echo '已安装') ✓"
    else
        log_warn "openssl 未安装 (将回退到 /dev/urandom 生成随机数)"
        log_warn "建议安装: apt-get install -y openssl 或 yum install -y openssl"
    fi

    # 7. gVisor 检查 (仅 --prod)
    if $PROD_MODE; then
        log_step "7. 检查 gVisor (runsc)"
        if docker info 2>/dev/null | grep -qi "runsc"; then
            log_info "gVisor (runsc) 已安装 ✓"
        else
            log_warn "gVisor (runsc) 未安装, APK 注入容器将使用 seccomp 兜底 (安全性略低)"
            log_warn "如需启用 APK 注入功能, 请运行: ./deploy.sh install-gvisor"
        fi
    fi

    # 8. Android 工具检查 (仅提示)
    log_step "8. 检查 Android 工具 (APK 注入功能, 可选)"
    local android_tools_ok=true
    for tool in zipalign apksigner aapt2; do
        if command -v "$tool" >/dev/null 2>&1; then
            log_info "$tool: $(command -v "$tool") ✓"
        elif ls /opt/android-sdk/build-tools/*/"$tool" >/dev/null 2>&1; then
            log_info "$tool: $(ls /opt/android-sdk/build-tools/*/"$tool" | head -n1) ✓"
        else
            log_warn "$tool 未找到 (启用 APK 注入时需要)"
            android_tools_ok=false
        fi
    done
    if [[ -f "$SCRIPT_DIR/apk-inject-service/tools/APKEditor.jar" ]]; then
        log_info "APKEditor.jar 已就位 ✓"
    else
        log_warn "APKEditor.jar 未找到 (运行 enable-apk-inject 时会自动下载)"
    fi

    # 总结
    log_step "诊断完成"
    if $has_error; then
        log_error "存在阻断性问题, 请修复后重试"
        exit 1
    fi
    log_info "环境就绪, 可执行: ./deploy.sh init && ./deploy.sh up"
}

# ==================== init 子命令 (FR-2) ====================
generate_root_env() {
    log_step "生成根目录 .env"
    local env_file=".env"

    if [[ -f "$env_file" ]] && ! $FORCE; then
        log_info ".env 已存在, 跳过 (用 --force 强制重新生成)"
        return 0
    fi

    local mysql_pwd minio_pwd apk_keystore_pwd
    mysql_pwd=$(random_hex 16)
    minio_pwd=$(random_hex 16)
    apk_keystore_pwd=$(random_hex 16)

    cat > "$env_file" <<EOF
# 自动生成于 $(date '+%Y-%m-%d %H:%M:%S') by deploy.sh
# 警告: 此文件包含敏感密钥, 请勿提交到版本控制 (已 .gitignore)

# MinIO 配置
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=${minio_pwd}
MINIO_BUCKET=card-auth

# MySQL (compose 用, server/.env 复用此密码)
MYSQL_ROOT_PASSWORD=${mysql_pwd}

# APK 签名 keystore
APK_KEYSTORE_PASSWORD=${apk_keystore_pwd}
APK_KEYSTORE_ALIAS=platform
EOF
    chmod 600 "$env_file"
    log_info "已生成 $env_file (权限 600)"
}

generate_server_env() {
    log_step "生成 server/.env"
    local env_file="server/.env"

    if [[ -f "$env_file" ]] && ! $FORCE; then
        log_info "server/.env 已存在, 跳过 (用 --force 强制重新生成)"
        return 0
    fi

    # 从根目录 .env 读取已生成的密钥 (确保跨文件一致)
    local mysql_pwd minio_pwd apk_keystore_pwd
    mysql_pwd="$(env_get MYSQL_ROOT_PASSWORD .env)" || die "无法读取根目录 .env 的 MYSQL_ROOT_PASSWORD"
    minio_pwd="$(env_get MINIO_ROOT_PASSWORD .env)" || die "无法读取根目录 .env 的 MINIO_ROOT_PASSWORD"
    apk_keystore_pwd="$(env_get APK_KEYSTORE_PASSWORD .env)" || die "无法读取根目录 .env 的 APK_KEYSTORE_PASSWORD"

    local app_secret jwt_secret app_debug
    app_secret=$(random_hex 24)
    jwt_secret=$(random_hex 32)
    if $PROD_MODE; then
        app_debug="false"
    else
        app_debug="true"
    fi

    cat > "$env_file" <<EOF
; 自动生成 by deploy.sh - 请勿手动编辑, 含敏感密钥
APP_DEBUG = ${app_debug}

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai
DEFAULT_LANG = zh-cn
APP_SECRET_KEY = ${app_secret}

[DATABASE]
TYPE = mysql
HOSTNAME = mysql
DATABASE = card_auth
USERNAME = root
PASSWORD = ${mysql_pwd}
HOSTPORT = 3306
CHARSET = utf8mb4
PREFIX = ca_

[REDIS]
HOST = redis
PORT = 6379
PASSWORD =
SELECT = 0

[CACHE]
DRIVER = redis

[JWT]
SECRET = ${jwt_secret}
EXPIRE = 7200
REFRESH_EXPIRE = 604800
ALGORITHM = HS256

[QUEUE]
DRIVER = redis

[MINIO]
ENDPOINT = http://minio:9000
BUCKET = card-auth
ACCESS_KEY = minioadmin
SECRET_KEY = ${minio_pwd}
USE_SSL = false

[APK_INJECT]
SERVICE_URL = http://apk-inject-service:8080
KEYSTORE_PASSWORD = ${apk_keystore_pwd}
KEYSTORE_ALIAS = platform
EOF
    chmod 600 "$env_file"
    log_info "已生成 $env_file (权限 600)"
}

generate_keystore() {
    log_step "生成 APK 签名 keystore"
    local keystore_dir="deploy/keystore"
    local keystore_file="${keystore_dir}/platform.keystore"

    if [[ -f "$keystore_file" ]]; then
        log_info "keystore 已存在, 跳过"
        return 0
    fi

    mkdir -p "$keystore_dir"
    local keystore_pwd
    keystore_pwd="$(env_get APK_KEYSTORE_PASSWORD .env)" || die "无法读取 .env 的 APK_KEYSTORE_PASSWORD"

    local dname="CN=CardAuth Platform, OU=Dev, O=CardAuth, L=Beijing, ST=Beijing, C=CN"

    if command -v keytool >/dev/null 2>&1; then
        log_info "使用本机 keytool 生成 keystore"
        keytool -genkeypair \
            -keystore "$keystore_file" \
            -alias platform \
            -keyalg RSA \
            -keysize 2048 \
            -validity 3650 \
            -storepass "$keystore_pwd" \
            -keypass "$keystore_pwd" \
            -dname "$dname"
    else
        log_info "本机无 keytool, 使用 Docker 容器 (eclipse-temurin:17-jre) 生成"
        docker run --rm \
            -v "${SCRIPT_DIR}/${keystore_dir}:/k" \
            -w /k \
            eclipse-temurin:17-jre \
            keytool -genkeypair \
                -keystore platform.keystore \
                -alias platform \
                -keyalg RSA \
                -keysize 2048 \
                -validity 3650 \
                -storepass "$keystore_pwd" \
                -keypass "$keystore_pwd" \
                -dname "$dname"
    fi

    # 修复属主: docker run 以 root 运行, 生成的文件属主为 root, 需 chown 回当前用户
    local cur_uid cur_gid
    cur_uid=$(id -u)
    cur_gid=$(id -g)
    if [[ $EUID -ne 0 ]]; then
        sudo chown "$cur_uid:$cur_gid" "$keystore_file" 2>/dev/null || true
    else
        chown "$cur_uid:$cur_gid" "$keystore_file" 2>/dev/null || true
    fi
    chmod 600 "$keystore_file"

    # 不再单独存档密码 (已在 .env 中, 避免重复存储扩大泄露面)
    log_info "已生成 $keystore_file (权限 600)"
    log_info "keystore 密码请查阅 .env 的 APK_KEYSTORE_PASSWORD"
}

build_frontend() {
    log_step "构建前端 (admin/dist)"
    local dist_file="admin/dist/index.html"

    if [[ -f "$dist_file" ]] && ! $FORCE; then
        log_info "admin/dist/index.html 已存在, 跳过 (用 --force 强制重新构建)"
        return 0
    fi

    if command -v npm >/dev/null 2>&1; then
        log_info "使用本机 npm 构建"
        (cd admin && npm ci && npm run build)
    else
        log_info "本机无 npm, 使用 Docker (node:20-alpine) 构建"
        docker run --rm \
            -v "${SCRIPT_DIR}/admin:/app" \
            -w /app \
            node:20-alpine \
            sh -c "npm ci && npm run build"
    fi

    # 修复属主: docker run 以 root 运行, 生成的 node_modules/dist 属主为 root
    # 非 root 用户后续无法删除, 需 chown 回当前用户
    if [[ $EUID -ne 0 ]]; then
        local cur_uid cur_gid
        cur_uid=$(id -u)
        cur_gid=$(id -g)
        sudo chown -R "$cur_uid:$cur_gid" "${SCRIPT_DIR}/admin/dist" "${SCRIPT_DIR}/admin/node_modules" 2>/dev/null || true
    fi

    [[ -f "$dist_file" ]] || die "前端构建失败: $dist_file 不存在"
    log_info "前端构建完成 ✓"
}

generate_ssl() {
    log_step "生成 SSL 自签证书 (仅 --prod)"
    local ssl_dir="docker/nginx/ssl"
    local crt_file="${ssl_dir}/server.crt"
    local key_file="${ssl_dir}/server.key"

    if [[ -f "$crt_file" && -f "$key_file" ]]; then
        log_info "SSL 证书已存在, 跳过"
        return 0
    fi

    mkdir -p "$ssl_dir"
    local cn="${DOMAIN:-$(hostname)}"
    log_info "使用 CN=${cn} 生成自签证书"
    openssl req -x509 -newkey rsa:2048 -nodes \
        -days 365 \
        -keyout "$key_file" \
        -out "$crt_file" \
        -subj "/CN=${cn}" 2>/dev/null
    chmod 600 "$key_file"
    log_warn "自签证书仅限测试, 生产请替换为正式证书 (Let's Encrypt / 商业 CA)"
    log_warn "证书路径: $crt_file / $key_file"
}

cmd_init() {
    log_step "初始化配置开始 (mode=$($PROD_MODE && echo prod || echo dev))"

    ensure_root_or_docker_group || true

    # 检测模式切换: 若已有 server/.env 且 APP_DEBUG 与目标模式不一致, 提示风险
    # --force 会重新生成所有密钥, MySQL/MinIO 数据卷将无法用新密码访问
    if [[ -f server/.env ]]; then
        local current_debug
        current_debug=$(env_get APP_DEBUG server/.env 2>/dev/null) || current_debug=""
        if [[ -n "$current_debug" ]]; then
            local target_debug="true"
            $PROD_MODE && target_debug="false"
            if [[ "$current_debug" != "$target_debug" ]]; then
                log_warn "检测到模式切换 (APP_DEBUG: $current_debug → $target_debug)"
                if $FORCE; then
                    log_warn "若用 --force 重新生成密钥, MySQL/MinIO 数据卷将无法用新密码访问"
                    log_warn "建议先执行: ./deploy.sh reset --yes 清理数据卷后再 init"
                    $ASSUME_YES || {
                        read -r -p "确认继续 --force 重新生成? [y/N] " ans
                        [[ "$ans" =~ ^[Yy]$ ]] || exit 0
                    }
                else
                    log_warn "未加 --force, 将保留现有配置不重新生成 (server/.env 跳过)"
                    log_warn "如需完整切换, 请先: ./deploy.sh reset --yes"
                fi
            fi
        fi
    fi

    generate_root_env
    generate_server_env
    generate_keystore
    build_frontend

    if $PROD_MODE; then
        generate_ssl
    fi

    log_step "初始化完成"
    log_info "生成的文件:"
    log_info "  - .env (根目录, 含 MySQL/MinIO/keystore 密钥)"
    log_info "  - server/.env (ThinkPHP 配置, 密钥与根目录一致)"
    log_info "  - deploy/keystore/platform.keystore (APK 签名)"
    log_info "  - admin/dist/ (前端构建产物)"
    $PROD_MODE && log_info "  - docker/nginx/ssl/server.{crt,key} (自签证书)"
    log_info "下一步: ./deploy.sh up"
}

# ==================== up 子命令 (FR-3) ====================
wait_mysql() {
    log_step "等待 MySQL 就绪"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    local max_wait=120
    local waited=0
    while ! $compose_cmd exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
        if [[ $waited -ge $max_wait ]]; then
            die "MySQL 在 ${max_wait}s 内未就绪"
        fi
        printf "."
        sleep 2
        waited=$((waited + 2))
    done
    echo ""
    log_info "MySQL 就绪 (${waited}s)"
}

install_php_deps() {
    log_step "安装 PHP 依赖 (composer)"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"

    # 检测 vendor 完整性: 目录存在 + autoload.php + installed.json 同时存在
    # 仅检测目录存在无法发现半成品 vendor (composer install 失败后残留)
    if $compose_cmd exec -T php-fpm sh -c "test -f /var/www/server/vendor/autoload.php && test -f /var/www/server/vendor/composer/installed.json" 2>/dev/null; then
        log_info "vendor 完整, 跳过"
        return 0
    fi

    # 清理残缺 vendor (目录存在但不完整)
    if $compose_cmd exec -T php-fpm sh -c "test -d /var/www/server/vendor" 2>/dev/null; then
        log_warn "vendor 残缺 (缺 autoload.php 或 installed.json), 清理后重装"
        $compose_cmd exec -T --user root php-fpm sh -c "rm -rf /var/www/server/vendor /var/www/server/composer.lock"
    fi

    # 用 --user root 执行 composer: 挂载目录属主为宿主机 root,
    # 容器内 www 用户无权写 composer.lock/vendor, 必须用 root
    # 同时设置 COMPOSER_ALLOW_SUPERUSER=1 避免 composer 警告
    local composer_flags=""
    if $PROD_MODE; then
        composer_flags="--no-dev --optimize-autoloader"
    fi
    $compose_cmd exec -T --user root -e COMPOSER_ALLOW_SUPERUSER=1 php-fpm \
        sh -c "cd /var/www/server && composer install $composer_flags"

    # vendor 由 root 创建, 后续 php-fpm (www 用户) 需读权限, 确保可读
    $compose_cmd exec -T --user root php-fpm \
        sh -c "cd /var/www/server && chmod -R a+r vendor && find vendor -type d -exec chmod a+x {} +"
    log_info "PHP 依赖安装完成 ✓"
}

fix_permissions() {
    log_step "修复目录权限"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    # runtime/public/uploads 需要 www 用户可写, 用 root 创建并开放权限
    $compose_cmd exec -T --user root php-fpm sh -c "cd /var/www/server && mkdir -p runtime public/uploads && chmod -R 777 runtime public/uploads"
    log_info "权限修复完成 ✓"
}

run_migrations() {
    log_step "执行数据库迁移"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    # 用 root 执行: think 命令可能写 vendor 缓存, 避免 www 用户权限问题
    $compose_cmd exec -T --user root php-fpm sh -c "cd /var/www/server && php think migrate:run"
    log_info "数据库迁移完成 ✓"
}

run_seeds() {
    log_step "执行数据填充 (按需)"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"

    local mysql_pwd
    mysql_pwd="$(env_get MYSQL_ROOT_PASSWORD .env)" || die "无法读取 .env 的 MYSQL_ROOT_PASSWORD"

    # 辅助函数: 查询表记录数 (失败返回 0, 不中断)
    _count_rows() {
        local table="$1"
        local count
        count=$($compose_cmd exec -T mysql mysql -uroot -p"${mysql_pwd}" card_auth \
            -N -B -e "SELECT COUNT(*) FROM ${table}" 2>/dev/null) || { echo "0"; return; }
        echo "${count:-0}"
    }

    # 辅助函数: 执行单个 seeder (失败仅 warn, 不中断)
    _run_seeder() {
        local seeder="$1"
        $compose_cmd exec -T --user root php-fpm sh -c "cd /var/www/server && php think seed:run -s ${seeder}" || {
            log_warn "${seeder} 执行失败"
            return 1
        }
        log_info "${seeder} 完成 ✓"
        return 0
    }

    # UserSeeder: 检测 admin 用户 (幂等, 可重复执行)
    local admin_count
    admin_count=$(_count_rows "ca_users WHERE username='admin'")
    if [[ "$admin_count" -eq 0 ]]; then
        _run_seeder "UserSeeder"
    else
        log_info "admin 用户已存在, 跳过 UserSeeder"
    fi

    # PackageSeeder: 检测 ca_packages 是否为空
    local pkg_count
    pkg_count=$(_count_rows "ca_packages")
    if [[ "$pkg_count" -eq 0 ]]; then
        _run_seeder "PackageSeeder"
    else
        log_info "ca_packages 已有数据, 跳过 PackageSeeder"
    fi

    # SystemConfigSeeder: 检测 ca_system_configs 是否为空
    local cfg_count
    cfg_count=$(_count_rows "ca_system_configs")
    if [[ "$cfg_count" -eq 0 ]]; then
        _run_seeder "SystemConfigSeeder"
    else
        log_info "ca_system_configs 已有数据, 跳过 SystemConfigSeeder"
    fi

    # AdminMenuSeeder: 检测 ca_admin_menus 是否为空
    local menu_count
    menu_count=$(_count_rows "ca_admin_menus")
    if [[ "$menu_count" -eq 0 ]]; then
        _run_seeder "AdminMenuSeeder"
    else
        log_info "ca_admin_menus 已有数据, 跳过 AdminMenuSeeder"
    fi

    log_info "数据填充检查完成 ✓"
}

start_services() {
    log_step "启动核心服务 (排除 APK 注入相关服务)"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"

    # 通过显式指定服务列表排除 APK 注入相关服务 (默认不启动)
    # docker compose up [SERVICE...] 仅启动指定服务及其依赖, 无需 --scale 0
    # (新版 compose v2 中 --scale service=0 会将服务标记为 disabled 并报错)
    local services="nginx php-fpm mysql redis minio minio-init"
    if $PROD_MODE; then
        # 生产环境额外启动通用定时任务调度器 (非 APK 专用)
        services="$services scheduler"
    fi

    # 若端口映射变化 (override 新生成/更新), 强制重建容器以应用新配置
    local recreate_flag=""
    if [[ "$FORCE_RECREATE" == "true" ]]; then
        recreate_flag="--force-recreate"
        log_info "检测到端口映射变化, 强制重建容器"
    fi

    # 第一次尝试启动
    local output
    if output=$($compose_cmd up -d $recreate_flag $services 2>&1); then
        log_info "核心服务已启动 ✓"
        return 0
    fi

    # 失败: 检测是否为容器名/网络冲突 (前次失败遗留)
    # "already in use by container" / "network with name ... already exists"
    if echo "$output" | grep -qE "already in use by container|network with name.*already exists"; then
        log_warn "检测到残留容器/网络冲突, 清理后重试"
        # 用 -f 强制移除残留容器 (停止+删除), 不删数据卷
        $compose_cmd down --remove-orphans 2>/dev/null || true
        # 兜底: 手动清理 compose 项目名下的容器 (compose down 偶尔遗漏)
        local orphan_containers
        orphan_containers=$(docker ps -a --filter "label=com.docker.compose.project=soup" --format '{{.Names}}' 2>/dev/null || echo "")
        if [[ -n "$orphan_containers" ]]; then
            echo "$orphan_containers" | xargs -r docker rm -f 2>/dev/null || true
        fi
        # 清理残留网络
        docker network rm soup_card-auth-network 2>/dev/null || true

        # 重新尝试启动
        if output=$($compose_cmd up -d --force-recreate $services 2>&1); then
            log_info "核心服务已启动 ✓ (清理残留后重试成功)"
            return 0
        fi
    fi

    # 失败: 检测是否为端口冲突 (address already in use / bind host port)
    if ! echo "$output" | grep -qE "address already in use|bind host port|failed to bind"; then
        # 非端口冲突错误, 直接输出并失败
        echo "$output" >&2
        die "启动服务失败 (非端口冲突, 见上方错误)"
    fi

    # 端口冲突: 从错误信息解析冲突端口 → 对应服务
    log_warn "检测到端口冲突, 自动生成 override 重试"
    local conflict_services=()
    if echo "$output" | grep -qE ":3306/"; then conflict_services+=("mysql"); fi
    if echo "$output" | grep -qE ":6379/"; then conflict_services+=("redis"); fi

    if [[ ${#conflict_services[@]} -eq 0 ]]; then
        echo "$output" >&2
        die "无法识别冲突端口 (仅自动处理 3306/6379, 其他端口请手动释放)"
    fi

    # 生成/更新 override 移除冲突服务的宿主机端口映射
    local override_file="docker-compose.override.yml"
    log_warn "将为以下服务移除宿主机端口映射: ${conflict_services[*]}"
    {
        echo "# 自动生成于 $(date '+%Y-%m-%d %H:%M:%S') by deploy.sh (start_services fallback)"
        echo "# 移除被外部占用的宿主机端口映射, 容器间通过 docker 网络通信不受影响"
        echo "services:"
        for svc in "${conflict_services[@]}"; do
            echo "  ${svc}:"
            echo "    ports: []"
        done
    } > "$override_file"
    log_info "已生成/更新 $override_file"

    # 重新获取 compose_cmd (会自动加载新 override) 并强制重建重试
    compose_cmd="$(get_compose_cmd)"
    if ! $compose_cmd up -d --force-recreate $services 2>&1; then
        echo "$output" >&2
        die "重试启动仍失败, 请手动检查端口占用: ss -tlnp | grep -E ':(3306|6379)'"
    fi
    log_info "核心服务已启动 ✓ (端口冲突已自动处理)"
}

# 检测端口冲突: 若宿主机 3306/6379 被非本平台容器占用, 自动生成
# docker-compose.override.yml 移除对应服务的宿主机端口映射
# (容器间通过 docker 网络通信不受影响, 仅移除 127.0.0.1:port 映射)
handle_port_conflicts() {
    log_step "检查端口冲突"
    local override_file="docker-compose.override.yml"

    # 服务名:端口 映射表 (仅检查有宿主机端口映射的服务)
    # nginx (8000/8080/80/443) 与 MinIO (9000/9001) 不在此处理:
    #   - nginx 端口是必须的对外服务端口, 冲突时应让用户决策
    #   - MinIO 控制台端口通常不冲突
    local check_items=("redis:6379" "mysql:3306")
    local conflict_services=()
    local need_override=false

    for item in "${check_items[@]}"; do
        local svc="${item%%:*}"
        local port="${item##*:}"
        if port_free "$port"; then
            log_info "$svc (端口 $port) 空闲 ✓"
            continue
        fi
        # 端口被占用, 判断是否为本平台容器且健康运行
        # (排除 Restarting/异常退出状态: 此时端口实际未绑定, 可能被外部进程抢占)
        local our_container
        case "$svc" in
            redis)  our_container="card-auth-redis" ;;
            mysql)  our_container="card-auth-mysql" ;;
            *)      our_container="" ;;
        esac
        if [[ -n "$our_container" ]]; then
            local container_status
            container_status=$(docker inspect --format '{{.State.Status}}' "$our_container" 2>/dev/null || echo "")
            if [[ "$container_status" == "running" ]]; then
                # 进一步验证容器确实绑定了该端口 (避免容器 running 但端口绑定失败的边缘情况)
                local port_bound
                port_bound=$(docker port "$our_container" 2>/dev/null | grep -q ":${port}" && echo "yes" || echo "no")
                if [[ "$port_bound" == "yes" ]]; then
                    log_info "$svc (端口 $port) 已被本平台容器健康占用 ✓"
                    continue
                fi
            fi
        fi
        # 被外部进程占用 (或本平台容器非 running 状态)
        log_warn "$svc 端口 $port 被外部进程占用 (或本平台容器未健康运行)"
        conflict_services+=("$svc")
        need_override=true
    done

    if ! $need_override; then
        log_info "无端口冲突 ✓"
        # 若之前生成过 override 但现在已无冲突, 不自动删除 (用户可能有意保留)
        return 0
    fi

    # 生成 override 文件移除冲突服务的端口映射
    log_warn "将为以下服务移除宿主机端口映射 (容器间通信不受影响): ${conflict_services[*]}"
    {
        echo "# 自动生成于 $(date '+%Y-%m-%d %H:%M:%S') by deploy.sh (handle_port_conflicts)"
        echo "# 移除被外部占用的宿主机端口映射, 容器间通过 docker 网络通信不受影响"
        echo "# 如需恢复宿主机端口映射, 删除本文件后释放对应端口再执行 ./deploy.sh up"
        echo "services:"
        for svc in "${conflict_services[@]}"; do
            echo "  ${svc}:"
            echo "    ports: []"
        done
    } > "$override_file"
    log_info "已生成 $override_file"
    log_warn "如需从宿主机直接访问 ${conflict_services[*]}, 请先释放端口或修改 docker-compose.yml"

    # 标记需要强制重建容器, 使新 override 的端口映射立即生效
    # (否则 compose 检测配置无变化不会重建, 运行中容器仍持有旧端口映射)
    FORCE_RECREATE=true
}

health_check() {
    log_step "健康检查"
    local be_url fe_url

    if $PROD_MODE; then
        be_url="https://localhost/"
        fe_url="https://localhost/admin"
    else
        be_url="http://localhost:8000/"
        fe_url="http://localhost:8080/"
    fi

    log_info "检查后端: $be_url"
    if $PROD_MODE; then
        if curl -sfk -o /dev/null "$be_url" 2>/dev/null; then
            log_info "后端健康检查通过 ✓"
        else
            log_warn "后端健康检查失败 (服务可能仍在启动, 稍后重试)"
        fi
    else
        if curl -sf -o /dev/null "$be_url" 2>/dev/null; then
            log_info "后端健康检查通过 ✓"
        else
            log_warn "后端健康检查失败 (服务可能仍在启动, 稍后重试)"
        fi
    fi

    if ! $PROD_MODE; then
        log_info "检查前端: $fe_url"
        if curl -sf -o /dev/null "$fe_url" 2>/dev/null; then
            log_info "前端健康检查通过 ✓"
        else
            log_warn "前端健康检查失败 (服务可能仍在启动)"
        fi
    fi
}

print_access_info() {
    log_step "部署完成 - 访问信息"
    local host="${DOMAIN}"
    if [[ -z "$host" ]]; then
        if [[ "$(uname)" == "Darwin" ]]; then
            # macOS: hostname -I 不支持, 用 ipconfig getifaddr
            host=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "localhost")
        else
            # Linux: hostname -I 输出所有 IP, 取第一个
            host=$(hostname -I 2>/dev/null | awk '{print $1}')
            host="${host:-localhost}"
        fi
    fi

    echo ""
    echo "========================================"
    echo "  卡密验证 SaaS 平台部署成功"
    echo "========================================"
    if $PROD_MODE; then
        echo "  后端 API:  https://${host}/"
        echo "  管理后台:  https://${host}/admin"
    else
        echo "  后端 API:  http://${host}:8000/"
        echo "  管理后台:  http://${host}:8080/"
    fi
    echo "  MinIO 控制台: http://${host}:9001/"
    echo ""
    echo "  默认账号: admin / admin123456"
    echo "========================================"
    log_warn "安全提示: 请立即修改默认密码 (登录后 → 个人中心)"
    log_info "其他命令:"
    log_info "  查看状态:   ./deploy.sh status"
    log_info "  查看日志:   ./deploy.sh logs"
    log_info "  启用APK注入: ./deploy.sh enable-apk-inject"
    log_info "  数据库备份: ./deploy.sh backup"
}

cmd_up() {
    log_step "启动服务 (mode=$($PROD_MODE && echo prod || echo dev))"

    ensure_root_or_docker_group || true

    # 重置标志, 由 handle_port_conflicts 根据实际情况设置
    FORCE_RECREATE=false

    # 配置必须先初始化
    if [[ ! -f .env ]] || [[ ! -f server/.env ]]; then
        die "配置文件缺失, 请先执行: ./deploy.sh init"
    fi

    # prod 模式下 gVisor 检查 (告警但不阻断)
    if $PROD_MODE; then
        if ! docker info 2>/dev/null | grep -qi "runsc"; then
            log_warn "gVisor (runsc) 未安装, APK 注入容器将使用 seccomp 兜底"
            log_warn "如需启用 gVisor: ./deploy.sh install-gvisor"
            log_warn "核心平台服务不依赖 gVisor, 继续启动..."
        fi
    fi

    # 端口冲突检测: 自动生成 override 文件移除被占用服务的宿主机端口映射
    handle_port_conflicts

    start_services
    wait_mysql
    install_php_deps
    fix_permissions
    run_migrations
    run_seeds
    health_check
    print_access_info
}

# ==================== down/status/logs/backup/reset (FR-4 ~ FR-8) ====================
cmd_down() {
    log_step "停止服务"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"

    # 用循环遍历所有参数, 不限于前两个位置 (支持 --yes down --volumes 等顺序)
    local has_volumes=false
    for arg in "${EXTRA_ARGS[@]:-}"; do
        [[ "$arg" == "--volumes" ]] && has_volumes=true
    done

    if $has_volumes; then
        if ! $ASSUME_YES; then
            die "删除数据卷是危险操作, 请加 --yes 确认: ./deploy.sh down --volumes --yes"
        fi
        $compose_cmd down -v
        log_info "服务已停止, 数据卷已删除"
    else
        $compose_cmd down
        log_info "服务已停止 (数据卷保留)"
    fi
}

cmd_status() {
    log_step "服务状态"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    $compose_cmd ps

    echo ""
    log_info "宿主机端口连通性 (若启用了端口冲突 override, mysql/redis 可能不监听宿主机):"
    local services=("mysql:3306" "redis:6379" "minio:9000")
    for svc in "${services[@]}"; do
        local name="${svc%%:*}"
        local port="${svc##*:}"
        if port_free "$port"; then
            log_warn "$name (宿主机端口 $port) 未监听 (可能被 override 移除或服务未启动)"
        else
            log_info "$name (宿主机端口 $port) 正常 ✓"
        fi
    done
}

cmd_logs() {
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    local service="${EXTRA_ARGS[0]:-}"
    if [[ -n "$service" ]]; then
        log_info "查看 $service 日志 (Ctrl+C 退出)"
        $compose_cmd logs --tail=100 -f "$service"
    else
        log_info "查看所有服务日志 (Ctrl+C 退出)"
        $compose_cmd logs --tail=100 -f
    fi
}

cmd_backup() {
    log_step "数据库备份"
    local backup_dir="${BACKUP_DIR:-/data/backups/mysql}"
    local compose_cmd
    compose_cmd="$(get_compose_cmd)"

    local mysql_pwd
    mysql_pwd="$(env_get MYSQL_ROOT_PASSWORD .env)" || die "无法读取 .env 的 MYSQL_ROOT_PASSWORD"

    mkdir -p "$backup_dir" && chmod 700 "$backup_dir"
    local date_str db_file
    date_str=$(date +%Y%m%d_%H%M%S)
    db_file="${backup_dir}/card_auth_${date_str}.sql"

    log_info "备份到: $db_file"
    $compose_cmd exec -T mysql mysqldump \
        -uroot -p"${mysql_pwd}" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --set-gtid-purged=OFF \
        --quick \
        card_auth > "$db_file"

    if [[ ! -s "$db_file" ]]; then
        die "备份失败: 文件为空"
    fi

    gzip "$db_file"
    log_info "备份成功: ${db_file}.gz ($(du -h "${db_file}.gz" | cut -f1))"

    # 清理 7 天前备份
    find "$backup_dir" -name "*.sql.gz" -mtime +7 -delete 2>/dev/null || true
    find "$backup_dir" -name "*.sql" -mtime +7 -delete 2>/dev/null || true
    log_info "已清理 7 天前的旧备份"
}

cmd_reset() {
    log_step "重置数据 (危险操作)"
    if ! $ASSUME_YES; then
        log_error "重置将删除所有数据卷, 不可恢复!"
        log_error "如确认执行, 请运行: ./deploy.sh reset --yes"
        exit 1
    fi

    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    log_warn "正在删除所有数据卷..."
    $compose_cmd down -v
    rm -rf server/runtime/
    log_info "重置完成, 请重新初始化: ./deploy.sh init && ./deploy.sh up"
}

# ==================== enable-apk-inject (FR-9) ====================
check_android_tools() {
    log_step "检查 Android 工具"
    local all_ok=true

    for tool in zipalign apksigner aapt2; do
        if command -v "$tool" >/dev/null 2>&1; then
            log_info "$tool: $(command -v "$tool") ✓"
        elif ls /opt/android-sdk/build-tools/*/"$tool" >/dev/null 2>&1; then
            log_info "$tool: $(ls /opt/android-sdk/build-tools/*/"$tool" | head -n1) ✓"
        else
            log_warn "$tool 未找到"
            all_ok=false
        fi
    done

    # APKEditor.jar 自动下载
    local apk_editor="apk-inject-service/tools/APKEditor.jar"
    if [[ -f "$apk_editor" ]]; then
        log_info "APKEditor.jar 已就位 ✓"
    else
        log_info "下载 APKEditor.jar..."
        mkdir -p apk-inject-service/tools
        if wget -q -O "$apk_editor" https://github.com/REAndroid/APKEditor/releases/latest/download/APKEditor.jar; then
            log_info "APKEditor.jar 下载完成 ✓"
        else
            log_error "APKEditor.jar 下载失败, 请手动下载到 $apk_editor"
            all_ok=false
        fi
    fi

    if ! $all_ok; then
        log_error "Android Build Tools 缺失, 请安装:"
        log_error "  sdkmanager \"build-tools;34.0.0\""
        log_error "  或从 https://developer.android.com/tools/releases/build-tools 下载"
        log_error "  并将 zipalign/apksigner/aapt2 加入 PATH"
        return 1
    fi
    return 0
}

cmd_enable_apk_inject() {
    log_step "启用 APK 云端注入功能"

    ensure_root_or_docker_group || true

    # 配置必须先初始化
    if [[ ! -f .env ]] || [[ ! -f server/.env ]]; then
        die "配置文件缺失, 请先执行: ./deploy.sh init"
    fi

    # 检查 Android 工具
    if ! check_android_tools; then
        die "Android 工具缺失, 无法启用 APK 注入功能"
    fi

    # prod 模式下检查 gVisor
    if $PROD_MODE; then
        if ! docker info 2>/dev/null | grep -qi "runsc"; then
            log_error "生产模式下 APK 注入容器需要 gVisor (runsc), 但未安装"
            die "请先运行: ./deploy.sh install-gvisor"
        fi
    fi

    local compose_cmd
    compose_cmd="$(get_compose_cmd)"
    log_info "启动 APK 注入相关服务..."
    $compose_cmd up -d apk-inject-service apk-queue-worker apk-scheduler

    # 健康检查
    log_step "等待 APK 注入服务就绪"
    local max_wait=60
    local waited=0
    while ! curl -sf http://localhost:8081/api/v1/health 2>/dev/null; do
        if [[ $waited -ge $max_wait ]]; then
            log_warn "APK 注入服务健康检查超时 (${max_wait}s), 请查看日志: ./deploy.sh logs apk-inject-service"
            return 1
        fi
        printf "."
        sleep 2
        waited=$((waited + 2))
    done
    echo ""
    log_info "APK 注入服务就绪 ✓"
    log_info "API: http://localhost:8081/api/v1/health"
    log_info "管理后台 → 商户中心 → APK 云注入 即可使用"
}

# ==================== install-gvisor (FR-10) ====================
cmd_install_gvisor() {
    log_step "安装 gVisor (runsc)"

    # 检测 OS
    if [[ ! -f /etc/os-release ]]; then
        die "无法检测操作系统 (缺少 /etc/os-release), 仅支持 Debian/Ubuntu"
    fi
    . /etc/os-release
    if [[ "$ID" != "debian" && "$ID" != "ubuntu" ]]; then
        die "仅支持 Debian/Ubuntu, 当前: $ID (其他系统请参考 https://gvisor.dev/docs/user_guide/install/)"
    fi

    log_info "检测到 $PRETTY_NAME, 开始安装 gVisor..."

    # 检查是否已安装
    if docker info 2>/dev/null | grep -qi "runsc"; then
        log_info "gVisor (runsc) 已安装 ✓"
        return 0
    fi

    # 警告: 安装会重启 Docker daemon, 中断所有运行中容器
    log_warn "安装将重启 Docker daemon, 所有运行中容器会被中断"
    if ! $ASSUME_YES; then
        if [[ $EUID -ne 0 ]]; then
            log_warn "安装 gVisor 需要 root 权限, 将使用 sudo"
        fi
        read -r -p "确认继续? [y/N] " ans
        [[ "$ans" =~ ^[Yy]$ ]] || { log_info "已取消"; exit 0; }
    fi

    # 添加 gVisor apt 仓库
    # set -euo pipefail: curl/gpg 失败时中断, 避免 apt-get install 未签名包
    # gpg --dearmor --yes: 避免已存在文件时交互式确认
    log_info "添加 gVisor 仓库..."
    sudo bash -c '
        set -euo pipefail
        curl -fsSL https://gvisor.dev/archive.key | gpg --dearmor --yes -o /usr/share/keyrings/gvisor-archive-keyring.gpg
        echo "deb [arch=amd64 signed-by=/usr/share/keyrings/gvisor-archive-keyring.gpg] https://storage.googleapis.com/gvisor/releases release main" > /etc/apt/sources.list.d/gvisor.list
        apt-get update
        apt-get install -y runsc
        runsc install
        systemctl restart docker
    '

    # 验证安装
    if docker info 2>/dev/null | grep -qi "runsc"; then
        log_info "gVisor (runsc) 安装成功 ✓"
        log_info "请重新运行: ./deploy.sh --prod"
    else
        die "gVisor 安装可能失败, 请检查 docker info 输出"
    fi
}

# ==================== 主入口 ====================
main() {
    # 解析全局参数 (在子命令之前)
    local args=("$@")
    local cmd=""
    local cmd_args=()
    local i=0

    # 先提取子命令之前的全局参数
    while [[ $i -lt ${#args[@]} ]]; do
        case "${args[$i]}" in
            --prod) PROD_MODE=true ;;
            --yes|-y) ASSUME_YES=true ;;
            --force) FORCE=true ;;
            --domain=*) DOMAIN="${args[$i]#*=}" ;;
            --branch=*) BRANCH="${args[$i]#*=}" ;;
            --help|-h)
                sed -n '2,15p' "$0"
                exit 0
                ;;
            *)
                # 第一个非 -- 开头的参数为子命令
                if [[ -z "$cmd" ]]; then
                    cmd="${args[$i]}"
                else
                    cmd_args+=("${args[$i]}")
                fi
                ;;
        esac
        i=$((i + 1))
    done

    # 设置 EXTRA_ARGS 给需要子命令参数的函数
    EXTRA_ARGS=("${cmd_args[@]}")

    case "$cmd" in
        "")
            # 无子命令, 默认执行 init && up
            cmd_init
            cmd_up
            ;;
        doctor)
            cmd_doctor
            ;;
        init)
            cmd_init
            ;;
        up)
            cmd_up
            ;;
        down)
            cmd_down
            ;;
        status)
            cmd_status
            ;;
        logs)
            cmd_logs
            ;;
        backup)
            cmd_backup
            ;;
        reset)
            cmd_reset
            ;;
        enable-apk-inject)
            cmd_enable_apk_inject
            ;;
        install-gvisor)
            cmd_install_gvisor
            ;;
        *)
            log_error "未知命令: $cmd"
            log_error "可用命令: doctor|init|up|down|status|logs|backup|reset|enable-apk-inject|install-gvisor"
            exit 1
            ;;
    esac
}

main "$@"
