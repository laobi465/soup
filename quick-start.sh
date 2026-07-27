#!/usr/bin/env bash
# quick-start.sh - 远程一键部署脚本
# 用法:
#   curl -fsSL https://raw.githubusercontent.com/laobi465/soup/main/quick-start.sh | bash
#   curl -fsSL https://raw.githubusercontent.com/laobi465/soup/main/quick-start.sh | bash -s -- --prod
#   curl -fsSL https://raw.githubusercontent.com/laobi465/soup/main/quick-start.sh | bash -s -- --branch=dev

set -euo pipefail

# ==================== 配置 ====================
INSTALL_DIR="/opt/soup"
REPO_URL="https://github.com/laobi465/soup.git"
DEFAULT_BRANCH="main"

# 参数
PROD_MODE=false
BRANCH="$DEFAULT_BRANCH"
EXTRA_DEPLOY_ARGS=()

# ==================== 日志 ====================
log_info()  { printf "\033[32m[%s] [INFO] %s\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
log_warn()  { printf "\033[33m[%s] [WARN] %s\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
log_error() { printf "\033[31m[%s] [ERROR] %s\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >&2; }
log_step()  { printf "\n\033[36m[%s] === %s ===\033[0m\n" "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
die() { log_error "$*"; exit 1; }

# ==================== 参数解析 ====================
parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --prod)
                PROD_MODE=true
                EXTRA_DEPLOY_ARGS+=("--prod")
                shift
                ;;
            --branch=*)
                BRANCH="${1#*=}"
                shift
                ;;
            --domain=*)
                EXTRA_DEPLOY_ARGS+=("$1")
                shift
                ;;
            --yes|-y)
                EXTRA_DEPLOY_ARGS+=("$1")
                shift
                ;;
            --force)
                EXTRA_DEPLOY_ARGS+=("$1")
                shift
                ;;
            --help|-h)
                cat <<EOF
用法: curl -fsSL <url> | bash -s -- [选项]

选项:
  --prod            生产模式部署 (含 SSL/80+443 端口)
  --branch=<name>   指定 git 分支 (默认: main)
  --domain=<domain> 生产模式 SSL 证书 CN
  --yes             跳过交互确认
  --force           强制重新生成配置

示例:
  开发环境: curl -fsSL <url> | bash
  生产环境: curl -fsSL <url> | bash -s -- --prod
  指定分支: curl -fsSL <url> | bash -s -- --branch=dev --prod
EOF
                exit 0
                ;;
            *)
                log_warn "未知参数: $1 (忽略)"
                shift
                ;;
        esac
    done
}

# ==================== 检测 OS ====================
detect_os() {
    if [[ ! -f /etc/os-release ]]; then
        die "无法检测操作系统 (缺少 /etc/os-release), 仅支持 Linux"
    fi
    . /etc/os-release
    log_info "操作系统: $PRETTY_NAME"
    case "$ID" in
        debian|ubuntu|centos|rhel|fedora|amzn|almalinux|rocky)
            log_info "支持的发行版 ✓"
            ;;
        *)
            log_warn "未明确支持的发行版: $ID, 将尝试通用安装方式"
            ;;
    esac
}

# ==================== 安装 Docker ====================
install_docker() {
    log_step "检查 Docker"
    if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
        log_info "Docker 已安装 ✓ ($(docker version --format '{{.Server.Version}}' 2>/dev/null | head -n1))"
        return 0
    fi

    log_info "Docker 未安装, 开始安装..."
    if curl -fsSL https://get.docker.com -o /tmp/get-docker.sh; then
        sh /tmp/get-docker.sh
    else
        die "Docker 安装脚本下载失败, 请手动安装: https://docs.docker.com/engine/install/"
    fi

    # 启动并设置开机自启
    if command -v systemctl >/dev/null 2>&1; then
        systemctl start docker
        systemctl enable docker
        log_info "Docker 服务已启动并设为开机自启 ✓"
    fi

    # 验证
    if ! docker info >/dev/null 2>&1; then
        die "Docker 安装后 daemon 仍未运行, 请手动启动: sudo systemctl start docker"
    fi
    log_info "Docker 安装完成 ✓"
}

# ==================== 检查权限 ====================
ensure_permissions() {
    log_step "检查权限"
    if [[ $EUID -eq 0 ]]; then
        log_info "以 root 运行 ✓"
        return 0
    fi

    if groups | grep -qw docker; then
        log_info "当前用户在 docker 组 ✓"
        return 0
    fi

    # 尝试加入 docker 组
    if [[ $EUID -eq 0 ]] || sudo -n true 2>/dev/null; then
        log_info "将当前用户加入 docker 组..."
        sudo usermod -aG docker "$USER" 2>/dev/null || true
        log_warn "请重新登录或执行 'newgrp docker' 使组变更生效"
        # 直接用 sudo 执行后续
        log_warn "后续命令将以 sudo 执行"
    else
        die "需要 root 或 sudo 权限来安装 Docker, 请使用: curl ... | sudo bash"
    fi
}

# ==================== 安装 git ====================
install_git() {
    log_step "检查 git"
    if command -v git >/dev/null 2>&1; then
        log_info "git 已安装 ✓"
        return 0
    fi

    log_info "安装 git..."
    if command -v apt-get >/dev/null 2>&1; then
        sudo apt-get update -qq && sudo apt-get install -y -qq git
    elif command -v yum >/dev/null 2>&1; then
        sudo yum install -y -q git
    elif command -v dnf >/dev/null 2>&1; then
        sudo dnf install -y -q git
    else
        die "无法自动安装 git, 请手动安装"
    fi
    log_info "git 安装完成 ✓"
}

# ==================== 克隆仓库 ====================
clone_repo() {
    log_step "克隆仓库到 $INSTALL_DIR"
    if [[ -d "$INSTALL_DIR/.git" ]]; then
        log_info "仓库已存在, 执行 git pull..."
        cd "$INSTALL_DIR"
        git fetch --all --prune
        git checkout "$BRANCH" 2>/dev/null || die "分支 $BRANCH 不存在"
        git pull origin "$BRANCH" || log_warn "git pull 失败, 继续使用本地代码"
    else
        log_info "克隆仓库: $REPO_URL (分支: $BRANCH)"
        # 创建父目录
        sudo mkdir -p "$(dirname "$INSTALL_DIR")"
        # 调整权限让当前用户可写
        sudo chown "$USER:" "$(dirname "$INSTALL_DIR")" 2>/dev/null || true
        git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$INSTALL_DIR"
        cd "$INSTALL_DIR"
    fi
    log_info "仓库就绪: $(pwd)"
}

# ==================== 主流程 ====================
main() {
    parse_args "$@"

    log_step "卡密验证 SaaS 平台 - 远程一键部署"
    log_info "安装目录: $INSTALL_DIR"
    log_info "分支: $BRANCH"
    log_info "模式: $($PROD_MODE && echo '生产 (prod)' || echo '开发 (dev)')"

    detect_os
    ensure_permissions
    install_git
    install_docker
    clone_repo

    # 执行 deploy.sh
    log_step "执行 deploy.sh init"
    chmod +x deploy.sh
    if [[ $EUID -eq 0 ]]; then
        ./deploy.sh init "${EXTRA_DEPLOY_ARGS[@]}"
    else
        sudo -E ./deploy.sh init "${EXTRA_DEPLOY_ARGS[@]}"
    fi

    log_step "执行 deploy.sh up"
    if [[ $EUID -eq 0 ]]; then
        ./deploy.sh up "${EXTRA_DEPLOY_ARGS[@]}"
    else
        sudo -E ./deploy.sh up "${EXTRA_DEPLOY_ARGS[@]}"
    fi

    log_step "部署流程完成"
    log_info "更多命令请在 $INSTALL_DIR 目录下执行:"
    log_info "  ./deploy.sh status              # 查看服务状态"
    log_info "  ./deploy.sh logs                # 查看日志"
    log_info "  ./deploy.sh backup              # 数据库备份"
    log_info "  ./deploy.sh enable-apk-inject   # 启用 APK 云注入功能"
    log_info "  ./deploy.sh --help              # 查看完整帮助"
}

main "$@"
