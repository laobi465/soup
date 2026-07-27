# 一键部署脚本 - Implementation Plan

## [ ] Task 1: deploy.sh 主框架 + 公共函数 + doctor 子命令 (FR-1)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 创建 `/workspace/deploy.sh`（可执行，shebang `#!/usr/bin/env bash`，`set -euo pipefail`）
  - 参数解析：支持 `--prod` / `--yes` / `--force` / `--domain=` / `--branch=` 全局参数与子命令
  - 无子命令时默认执行 `init && up`（一键流程）
  - 公共函数库（内联在脚本头部）：
    - `log_info/log_warn/log_error/log_step`：带时间戳与颜色的日志（绿/黄/红）
    - `detect_compose()`：检测 `docker compose`（v2）或 `docker-compose`（v1），返回命令字符串
    - `ensure_root_or_docker_group()`：检查 docker 权限
    - `random_hex(len)`：`openssl rand -hex $len`
  - `doctor` 子命令实现：
    - Docker 版本检查（≥ 20.10）
    - Docker Compose 可用性
    - docker 权限检查
    - 端口占用检查（dev/prod 不同端口集，用 `ss -tlnp` 或 `lsof`）
    - 磁盘空间检查（≥ 5GB，`df`）
    - gVisor 检查（仅 --prod）：`docker info | grep -i runsc`
    - 输出三色诊断报告
- **Acceptance Criteria Addressed**: 支撑 AC-1, AC-5
- **Test Requirements**:
  - `programmatic` TR-1.1: `./deploy.sh doctor` 在已装 Docker 环境返回 exit 0
  - `programmatic` TR-1.2: 端口被占用时输出黄色警告
  - `programmatic` TR-1.3: gVisor 未装时（--prod）输出黄色警告而非失败
- **Notes**: 主框架是后续所有子命令的基础

## [ ] Task 2: init 子命令 - 配置生成 (FR-2)
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - `generate_root_env()`：
    - 如 `.env` 已存在且无 `--force`，跳过并提示
    - 生成 `MYSQL_ROOT_PASSWORD` / `MINIO_ROOT_PASSWORD` / `APK_KEYSTORE_PASSWORD`（各 `openssl rand -hex 16`）
    - 从 `.env.example` 模板渲染，替换占位符
    - `chmod 600 .env`
  - `generate_server_env()`：
    - 如 `server/.env` 已存在且无 `--force`，跳过
    - 从根目录 `.env` 读取已生成密钥（确保跨文件一致）
    - 生成 `APP_SECRET_KEY`（`openssl rand -hex 24`）、`JWT.SECRET`（`openssl rand -hex 16`）
    - `APP_DEBUG` 按 --prod 设置
    - `QUEUE.DRIVER=redis`
    - `DATABASE.PASSWORD` / `MINIO.SECRET_KEY` / `APK_INJECT.KEYSTORE_PASSWORD` 复用根目录值
    - `chmod 600 server/.env`
  - `generate_keystore()`：
    - 如 `deploy/keystore/platform.keystore` 已存在，跳过
    - 优先本机 `keytool`；无则 `docker run --rm -v ... eclipse-temurin:17-jre keytool`
    - 用 `.env` 中的 `APK_KEYSTORE_PASSWORD` 作为 storepass/keypass
    - 密码写入 `deploy/keystore/.keystore-password.txt`（`chmod 600`）
  - `build_frontend()`：
    - 如 `admin/dist/index.html` 已存在且无 `--force`，跳过
    - 本机有 `npm` → `cd admin && npm ci && npm run build`
    - 无 npm → `docker run --rm -v $(pwd)/admin:/app -w /app node:20-alpine sh -c "npm ci && npm run build"`
  - `generate_ssl()`（仅 --prod）：
    - 如 `docker/nginx/ssl/server.crt` 已存在，跳过
    - `mkdir -p docker/nginx/ssl`
    - `openssl req -x509 -newkey rsa:2048 -nodes -days 365 -keyout ... -out ... -subj "/CN=${DOMAIN:-$(hostname)}"`
    - 告警"自签证书仅限测试"
  - 输出初始化摘要（不回显密钥明文）
- **Acceptance Criteria Addressed**: AC-2, AC-4
- **Test Requirements**:
  - `programmatic` TR-2.1: `.env` 与 `server/.env` 的 `MYSQL_ROOT_PASSWORD` 一致
  - `programmatic` TR-2.2: 所有密钥长度 ≥ 32 字符
  - `programmatic` TR-2.3: `deploy/keystore/platform.keystore` 存在且 `keytool -list` 能读取
  - `programmatic` TR-2.4: `admin/dist/index.html` 存在
  - `programmatic` TR-2.5: 重复执行 init 不覆盖已有 .env（幂等）
  - `programmatic` TR-2.6: `--prod` 时 `docker/nginx/ssl/server.crt` 存在
- **Notes**: 跨文件密码一致是核心安全要求

## [ ] Task 3: up 子命令 - 启动与初始化 (FR-3)
- **Priority**: high
- **Depends On**: Task 2
- **Description**:
  - `start_services()`：
    - `$COMPOSE up -d`（dev）或 `$COMPOSE -f docker-compose.prod.yml up -d`（prod）
    - 但需排除 APK 注入相关服务（默认不启动），用 `--scale apk-inject-service=0 --scale apk-queue-worker=0 --scale apk-scheduler=0` 或单独 profile
  - `wait_mysql()`：
    - 循环 `$COMPOSE exec -T mysql mysqladmin ping -h localhost --silent`，间隔 2 秒，超时 120 秒
    - 超时则 log_error 并退出
  - `install_php_deps()`：
    - `$COMPOSE exec -T php-fpm sh -c "test -d vendor || composer install --no-dev $(prod_flag)"`
    - prod_flag = `--optimize-autoloader`
  - `fix_permissions()`：
    - `$COMPOSE exec -T php-fpm sh -c "mkdir -p runtime public/uploads && chmod -R 777 runtime public/uploads"`
    - 注意：php-fpm 容器 USER www，可能需 `--user root` 执行 chmod
  - `run_migrations()`：
    - `$COMPOSE exec -T php-fpm php think migrate:run`
  - `run_seeds()`：
    - 检测 admin 用户是否存在（`php think seed:run` 前查 `ca_users` 表），已存在则跳过
    - 或直接 `php think seed:run`（Seeder 应幂等，但当前 UserSeeder 非幂等，需包裹 try-catch）
  - `health_check()`：
    - 后端：`curl -sf http://localhost:8000/`（dev）/ `curl -sfk https://localhost/`（prod）
    - 前端：`curl -sf http://localhost:8080/`（dev）
    - 失败则 log_warn（不阻断，因可能需要更多启动时间）
  - 输出访问地址表 + 默认账号 + 安全提示
- **Acceptance Criteria Addressed**: AC-1, AC-3
- **Test Requirements**:
  - `programmatic` TR-3.1: MySQL 未就绪时脚本等待而非立即失败
  - `programmatic` TR-3.2: 迁移执行后 `ca_users` 表存在
  - `programmatic` TR-3.3: seed 执行后 admin 用户存在
  - `programmatic` TR-3.4: `curl http://localhost:8000/` 返回 200
  - `programmatic` TR-3.5: 默认不启动 APK 注入服务（apk-inject-service 容器不存在）
- **Notes**: UserSeeder 非幂等，需处理重复执行

## [ ] Task 4: down/status/logs/backup/reset 子命令 (FR-4 ~ FR-8)
- **Priority**: medium
- **Depends On**: Task 1
- **Description**:
  - `down`：`$COMPOSE down`，`--volumes` 需 `--yes` 确认
  - `status`：`$COMPOSE ps` + 端口连通性检查（mysql/redis/minio）
  - `logs [service]`：`$COMPOSE logs --tail=100 -f ${service:-}`
  - `backup`：
    - 从 `.env` 读取 `MYSQL_ROOT_PASSWORD`（修复 backup.sh 硬编码 root123456）
    - 通过 `$COMPOSE exec -T mysql mysqldump ...` 执行，输出到 `/data/backups/mysql/`
    - 保留 7 天清理
  - `reset`：
    - 必须传 `--yes`，否则提示并退出
    - `$COMPOSE down -v` 删除数据卷
    - `rm -rf server/runtime/`
    - 提示重新 `./deploy.sh init && ./deploy.sh up`
- **Acceptance Criteria Addressed**: AC-8
- **Test Requirements**:
  - `programmatic` TR-4.1: `reset` 无 `--yes` 时退出码非 0 且不删数据
  - `programmatic` TR-4.2: `backup` 生成的 sql 文件非空且可恢复
  - `programmatic` TR-4.3: `status` 显示各服务运行状态
- **Notes**: backup 需修复密码读取问题

## [ ] Task 5: enable-apk-inject 子命令 (FR-9)
- **Priority**: medium
- **Depends On**: Task 3
- **Description**:
  - `check_android_tools()`：
    - 检查 `zipalign` / `apksigner` / `aapt2` 是否在 PATH 或 `/opt/android-sdk/build-tools/*/`
    - 检查 `APKEditor.jar` 是否在 `apk-inject-service/tools/`
  - 缺失处理：
    - APKEditor.jar：`wget -O apk-inject-service/tools/APKEditor.jar https://github.com/REAndroid/APKEditor/releases/latest/download/APKEditor.jar`
    - Android Build Tools：提示安装命令 `sdkmanager "build-tools;34.0.0"` 或手动下载路径，不自动安装（需 Android SDK 基础环境）
  - 工具就绪后：
    - `$COMPOSE up -d apk-inject-service apk-queue-worker apk-scheduler`
    - 等待 `curl -sf http://localhost:8081/api/v1/health` 成功
    - 输出 APK 注入功能已启用提示
- **Acceptance Criteria Addressed**: AC-6
- **Test Requirements**:
  - `programmatic` TR-5.1: Android 工具缺失时脚本提示并辅助下载 APKEditor
  - `programmatic` TR-5.2: 工具就绪后 3 个 APK 服务启动
  - `programmatic` TR-5.3: `curl http://localhost:8081/api/v1/health` 返回 200
- **Notes**: Android Build Tools 安装较重，仅提示不自动装

## [ ] Task 6: install-gvisor 子命令 + 生产降级 (FR-10, AC-5)
- **Priority**: medium
- **Depends On**: Task 1
- **Description**:
  - `install-gvisor`：
    - 检测 `/etc/os-release` 是否 Debian/Ubuntu，否则报错"仅支持 Debian/Ubuntu"
    - 按 gVisor 官方文档安装 runsc（apt 仓库）
    - `sudo systemctl restart docker`
    - 提示重新运行 `./deploy.sh --prod`
  - `up --prod` 时的降级逻辑：
    - 启动前检测 `docker info | grep -i runsc`
    - 未装时 log_warn"gVisor 未安装，APK 注入容器将使用 seccomp 兜底（安全性略低）"
    - 核心平台服务正常启动（不依赖 gVisor）
    - 若用户尝试 enable-apk-inject 且未装 runsc，提示先运行 install-gvisor
- **Acceptance Criteria Addressed**: AC-5
- **Test Requirements**:
  - `programmatic` TR-6.1: 未装 runsc 时 `--prod` 启动核心平台成功（APK 服务不启动）
  - `programmatic` TR-6.2: install-gvisor 在非 Debian/Ubuntu 报错退出
- **Notes**: install-gvisor 需 sudo

## [ ] Task 7: quick-start.sh 远程一键脚本 (FR-11)
- **Priority**: medium
- **Depends On**: Task 1, 2, 3
- **Description**:
  - 创建 `/workspace/quick-start.sh`（可执行）
  - 流程：
    1. 检测 Docker，未装则 `curl -fsSL https://get.docker.com | sh`（Debian/Ubuntu/CentOS）
    2. 启动 docker 服务 `sudo systemctl start docker && sudo systemctl enable docker`
    3. 克隆仓库：`git clone https://github.com/laobi465/soup /opt/soup`（已存在则 `cd /opt/soup && git pull`）
    4. `cd /opt/soup && chmod +x deploy.sh && ./deploy.sh init ${PROD_FLAG} && ./deploy.sh up ${PROD_FLAG}`
    5. 打印访问地址 + 默认账号
  - 参数透传：`--prod` / `--branch=<branch>`
  - 错误处理：任一步失败打印日志并退出，不继续
- **Acceptance Criteria Addressed**: AC-1
- **Test Requirements**:
  - `programmatic` TR-7.1: 在干净 Debian 12 上 curl|bash 成功部署
  - `programmatic` TR-7.2: Docker 未装时自动安装
  - `programmatic` TR-7.3: 仓库已存在时 git pull 而非报错
- **Notes**: 需 sudo 权限安装 Docker 与写 /opt

## [ ] Task 8: 文档更新与端到端验证
- **Priority**: medium
- **Depends On**: Task 1-7
- **Description**:
  - 更新 `README.md` 的"快速开始"章节，替换为 `./deploy.sh` 一键命令（保留手动步骤作为备选）
  - 更新 `docs/宝塔面板部署指南.md` 交叉引用 deploy.sh
  - 端到端验证：
    - 干净环境 `./deploy.sh` 全流程通过
    - `./deploy.sh --prod` 全流程通过（含 SSL 自签）
    - `./deploy.sh enable-apk-inject` 启用注入
    - `./deploy.sh reset --yes` 重置后重新部署
    - 幂等性：连续两次 `./deploy.sh` 第二次跳过已完成步骤
  - 提交代码并推送 GitHub main
- **Acceptance Criteria Addressed**: AC-1, AC-7
- **Test Requirements**:
  - `programmatic` TR-8.1: README 快速开始章节含 `./deploy.sh` 命令
  - `programmatic` TR-8.2: 端到端 dev 部署成功
  - `programmatic` TR-8.3: 端到端 prod 部署成功
  - `programmatic` TR-8.4: 幂等性验证通过
  - `programmatic` TR-8.5: 代码推送 GitHub main
- **Notes**: 最终验证任务
