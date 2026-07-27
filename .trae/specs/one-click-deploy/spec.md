# 一键部署脚本 - PRD

## Overview
- **Summary**: 为卡密验证 SaaS 平台提供一键部署脚本 `deploy.sh`，将当前分散的 10+ 步手动部署（双 .env 配置、密钥生成、keystore 生成、composer install、npm build、docker compose up、等待 MySQL、migrate、seed）收敛为单条命令；APK 云端注入作为可选功能按需启用。
- **Purpose**: 降低部署门槛至"克隆即用"，消除因手动配置遗漏密钥/未等待就绪/密码不一致导致的部署失败与安全风险。
- **Target Users**: 平台运维（生产部署）、开发者（本地起服务）、商户自部署（自助上线）

## Background & Context
- **现状**: `README.md` 的"快速开始"列了 6 步手动操作，每步都可能失败：
  1. 根目录 `.env` 与 `server/.env` 两个文件需手动复制并填值，且密码必须跨文件一致（`MYSQL_ROOT_PASSWORD` / `MINIO_ROOT_PASSWORD` / `APK_KEYSTORE_PASSWORD` 在两处都要填）
  2. `server/.env` 含 5 个敏感密钥（`APP_SECRET_KEY` / `JWT.SECRET` / `MYSQL_ROOT_PASSWORD` / `MINIO_ROOT_PASSWORD` / `APK_KEYSTORE_PASSWORD`），用户常填弱密码或占位符
  3. `deploy/keystore/platform.keystore` 需手动 `keytool` 生成，密码需与 `.env` 联动
  4. `docker compose exec php-fpm composer install` 需在容器启动后手动执行
  5. 前端 `admin/dist` 需本机有 Node.js 才能构建，否则 nginx 挂载空目录
  6. `php think migrate:run` 在 MySQL 未就绪时直接失败（docker compose up 不会等 MySQL 完全启动）
  7. APK 注入微服务依赖外部 Android 工具（zipalign/apksigner/aapt2/APKEditor.jar），Dockerfile 未内置，缺失时注入任务必然失败
  8. 生产环境 `docker-compose.prod.yml` 的 `runtime: runsc` 需宿主机预装 gVisor，未安装时容器启动报错
  9. 生产 nginx 引用 `./docker/nginx/ssl/server.crt|key`，无证书时 nginx 无法启动
- **根因**: 缺少一个编排脚本统一处理"配置生成 → 依赖安装 → 服务启动 → 就绪等待 → 数据初始化 → 健康检查"全链路

## Goals
- 提供单条命令 `./deploy.sh` 完成核心平台（卡密验证 + 管理后台）从零到可用
- 自动生成所有敏感密钥（随机 32+ 字符），跨文件一致，零手动填值
- 自动生成 keystore、自动构建前端、自动等待 MySQL 就绪后执行迁移与填充
- APK 云端注入作为可选功能，`./deploy.sh enable-apk-inject` 按需启用
- 生产环境自动降级：gVisor 未装 → seccomp 兜底；SSL 缺失 → 自签证书（告警）
- 提供远程一键安装命令（curl|bash），克隆+初始化+启动一条龙

## Non-Goals (Out of Scope)
- Kubernetes / Helm 部署（MVP 仅 Docker Compose）
- 多节点集群部署（单机为主）
- 持续集成 CI/CD pipeline（属另一工作流）
- 数据迁移/灰度发布（属运维平台范畴）
- 宝塔面板部署增强（已有 `docs/宝塔面板部署指南.md`，不重复）

## 推荐的一键部署命令（最终用户体验）

### 场景 A：远程一键（新服务器，最快）
```bash
curl -fsSL https://raw.githubusercontent.com/laobi465/soup/main/quick-start.sh | bash
```
> 自动克隆仓库到 `/opt/soup`，生成配置，启动核心平台，打印访问地址与默认账号。

### 场景 B：已克隆仓库
```bash
# 开发环境（端口 8000/8080，APP_DEBUG=true）
./deploy.sh

# 生产环境（端口 80/443，APP_DEBUG=false，含 gVisor/SSL）
./deploy.sh --prod

# 启用 APK 云端注入功能（按需）
./deploy.sh enable-apk-inject
```

### 场景 C：子命令模式（精细控制）
```bash
./deploy.sh doctor              # 环境诊断
./deploy.sh init [--prod]       # 仅初始化配置（.env/keystore/前端构建）
./deploy.sh up [--prod]         # 仅启动服务（含就绪等待+迁移+填充）
./deploy.sh down [--prod]       # 停止服务
./deploy.sh status              # 查看服务状态与端口
./deploy.sh logs [service]      # 查看日志
./deploy.sh backup              # 数据库备份
./deploy.sh reset --yes         # 危险：清空数据卷并重新初始化
./deploy.sh install-gvisor      # 辅助安装 gVisor（Debian/Ubuntu）
```

## Functional Requirements

### FR-1: `doctor` 环境诊断
- 检查 Docker 20.10+ 与 Docker Compose v2+ 是否安装
- 检查当前用户是否有 docker 权限（非 root 时是否在 docker 组）
- 检查端口占用（dev: 8000/8080/3306/6379/9000/9001/8081；prod: 80/443/3306/6379/9000/9001/8081）
- 检查磁盘可用空间 ≥ 5GB
- 检查 gVisor（仅 --prod）：`docker info | grep Runtimes` 是否含 `runsc`
- 检查 Android 工具（仅启用 APK 注入时）：`zipalign`/`apksigner`/`aapt2` 是否在 PATH 或 `/opt/android-sdk/build-tools/`
- 输出绿/黄/红三色诊断报告，黄色=警告可继续，红色=阻断需解决

### FR-2: `init` 初始化配置
- **幂等**：已存在的配置不覆盖，除非 `--force`
- **.env 生成**（根目录）：
  - `MYSQL_ROOT_PASSWORD`：`openssl rand -hex 16`（32 字符）
  - `MINIO_ROOT_USER`：默认 `minioadmin`
  - `MINIO_ROOT_PASSWORD`：`openssl rand -hex 16`
  - `MINIO_BUCKET`：默认 `card-auth`
  - `APK_KEYSTORE_PASSWORD`：`openssl rand -hex 16`（与 keystore 生成联动）
  - `APK_KEYSTORE_ALIAS`：默认 `platform`
- **server/.env 生成**：从根目录 .env 读取已生成密钥，确保跨文件一致
  - `APP_DEBUG`：dev=true / prod=false
  - `APP_SECRET_KEY`：`openssl rand -hex 24`（48 字符）
  - `DATABASE.PASSWORD`：复用 `MYSQL_ROOT_PASSWORD`
  - `REDIS.PASSWORD`：默认空（dev），prod 可选配置
  - `JWT.SECRET`：`openssl rand -hex 16`
  - `QUEUE.DRIVER`：`redis`（dev/prod 均用 redis，保障 APK 注入队列）
  - `MINIO.SECRET_KEY`：复用 `MINIO_ROOT_PASSWORD`
  - `APK_INJECT.KEYSTORE_PASSWORD`：复用 `APK_KEYSTORE_PASSWORD`
- **keystore 生成**（如不存在）：
  - 优先用本机 `keytool`（JRE/JDK 自带）
  - 本机无 keytool 时用 Docker：`docker run --rm -v $(pwd)/deploy/keystore:/k eclipse-temurin:17-jre keytool ...`
  - 生成后将密码写入 `deploy/keystore/.keystore-password.txt`（已 gitignore）
- **前端构建**（`admin/dist`）：
  - 优先本机 `npm ci && npm run build`（更快）
  - 本机无 Node.js 时用 Docker：`docker run --rm -v $(pwd)/admin:/app -w /app node:20-alpine sh -c "npm ci && npm run build"`
- **SSL 证书**（仅 --prod，如 `docker/nginx/ssl/` 无证书）：
  - 用 `openssl req -x509` 自签生成（有效期 1 年）
  - CN 取服务器 hostname 或用户通过 `--domain=example.com` 指定
  - 告警提示"自签证书仅限测试，生产请替换为正式证书"
- 输出初始化摘要：生成的文件列表、密钥已自动填充（不回显明文，仅提示"已生成"）

### FR-3: `up` 启动服务
- 调用 `docker compose up -d`（dev）或 `docker compose -f docker-compose.prod.yml up -d`（prod）
- **等待 MySQL 就绪**：循环 `docker compose exec -T mysql mysqladmin ping`，超时 120 秒报错
- **PHP 依赖安装**：`docker compose exec -T php-fpm composer install --no-dev --optimize-autoloader`（prod）/ `composer install`（dev），若 vendor 已存在则跳过
- **数据库迁移**：`docker compose exec -T php-fpm php think migrate:run`
- **数据填充**：`docker compose exec -T php-fpm php think seed:run`（仅首次，检测 admin 用户是否存在）
- **目录权限修复**：`docker compose exec -T php-fpm sh -c "mkdir -p runtime public/uploads && chmod -R 777 runtime public/uploads"`（ThinkPHP runtime 需可写）
- **MinIO 初始化**：依赖 `minio-init` 服务自动执行（已有 lifecycle 配置）
- **健康检查**：
  - 后端 API：`curl -sf http://localhost:8000/`（dev）/ `https://localhost/`（prod，-k 跳过自签校验）
  - 前端：`curl -sf http://localhost:8080/`（dev）/ `https://localhost/admin`（prod）
  - APK 注入服务（如启用）：`curl -sf http://localhost:8081/api/v1/health`
- 输出访问地址表 + 默认账号（admin / admin123456）+ 安全提示（"请立即修改默认密码"）

### FR-4: `down` 停止服务
- `docker compose down`（保留数据卷）
- 可选 `--volumes` 同时删除数据卷（危险，需 `--yes` 二次确认）

### FR-5: `status` 状态查看
- `docker compose ps` 格式化输出
- 每个服务：运行状态、端口映射、健康状态
- 核心依赖服务（mysql/redis/minio）的连通性 ping

### FR-6: `logs` 日志查看
- `docker compose logs --tail=100 -f [service]`
- 无参数时显示所有服务最近日志

### FR-7: `backup` 数据库备份
- 复用 `scripts/backup.sh`，但密码从 `.env` 读取（修复当前硬编码 `root123456` 问题）
- 备份目录默认 `/data/backups/mysql`，可通过 `--dir=` 自定义
- 保留 7 天自动清理

### FR-8: `reset` 重置数据
- **危险操作**，必须 `--yes` 显式确认
- `docker compose down -v` 删除所有数据卷
- 删除 `server/runtime/`、`admin/dist/`（可选保留构建产物）
- 重新执行 init + up

### FR-9: `enable-apk-inject` 启用 APK 注入
- 检测 Android 工具是否可用：
  - `zipalign`、`apksigner`、`aapt2` 在 PATH 或 `/opt/android-sdk/build-tools/*/`
  - `APKEditor.jar` 在 `apk-inject-service/tools/` 或 `/opt/APKEditor.jar`
- 缺失时提供下载辅助：
  - Android Build Tools：提示从 https://developer.android.com/tools/releases/build-tools 下载，或用 `sdkmanager "build-tools;34.0.0"`
  - APKEditor：`wget -O apk-inject-service/tools/APKEditor.jar https://github.com/REAndroid/APKEditor/releases/latest/download/APKEditor.jar`
- 工具就绪后启动 `apk-inject-service`、`apk-queue-worker`、`apk-scheduler` 三个服务
- 执行健康检查 `curl http://localhost:8081/api/v1/health`

### FR-10: `install-gvisor` 辅助安装 gVisor
- 仅支持 Debian/Ubuntu（检测 `/etc/os-release`）
- 安装 runsc：按官方文档 `curl -fsSL https://gvisor.dev/archive.key | sudo gpg --dearmor -o /usr/share/keyrings/gvisor-archive-keyring.gpg && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/gvisor-archive-keyring.gpg] https://storage.googleapis.com/gvisor/releases release main" | sudo tee /etc/apt/sources.list.d/gvisor.list && sudo apt-get update && sudo apt-get install -y runsc && sudo systemctl restart docker`
- 安装后提示重新运行 `./deploy.sh --prod`

### FR-11: `quick-start.sh` 远程一键
- 检测 Docker 未安装时自动安装（Debian/Ubuntu 用官方 get.docker.com）
- 克隆仓库到 `/opt/soup`（已存在则 `git pull`）
- 切换到目录，执行 `./deploy.sh init && ./deploy.sh up`
- 成功后打印访问地址与默认账号
- 支持 `--prod` 与 `--branch=` 参数透传

## Non-Functional Requirements
- **幂等性**：`init` 与 `up` 可重复执行，已完成的步骤跳过
- **兼容性**：Linux（Debian/Ubuntu/CentOS）与 macOS；Docker Compose v1（`docker-compose`）与 v2（`docker compose`）均兼容
- **非交互**：默认全自动，无需用户中途输入；敏感决策通过参数传入（`--yes`/`--prod`/`--domain=`）
- **降级安全**：gVisor/SSL/Android 工具缺失时给出明确降级方案而非直接失败（核心平台不依赖这些）
- **日志清晰**：每步打印 `[时间] [步骤] 状态` 格式，错误时给出修复建议
- **密钥安全**：生成的密钥不回显到终端，仅写入 .env（权限 600）；keystore 密码单独存 `.keystore-password.txt`（权限 600）
- **执行时长**：首次 init ≤ 5 分钟（不含 Docker 镜像拉取），up ≤ 3 分钟

## Constraints
- 不修改现有 `docker-compose.yml` / `docker-compose.prod.yml` 的服务定义（脚本作为编排层调用）
- 不引入额外运行时依赖（纯 bash + docker + openssl + keytool/npm 可选 Docker 兜底）
- 保留现有目录结构（`deploy.sh` 置于仓库根目录）
- `server/.env` 与根目录 `.env` 的密码必须由脚本统一生成、保持一致，禁止用户手动分别填写

## Acceptance Criteria

### AC-1: 全新服务器一键部署
- **Given**: 一台仅装 Docker 的 Debian 12 服务器
- **When**: 执行 `curl -fsSL https://raw.githubusercontent.com/laobi465/soup/main/quick-start.sh | bash`
- **Then**: 5 分钟内核心平台可访问，`curl http://<服务器IP>:8000/` 返回正常，管理后台 `http://<服务器IP>:8080/` 可登录
- **Verification**: programmatic（curl 检查 + 登录接口返回 JWT）

### AC-2: 密钥自动生成与一致
- **Given**: 执行 `./deploy.sh init`
- **When**: 检查根目录 `.env` 与 `server/.env`
- **Then**: 两文件的 `MYSQL_ROOT_PASSWORD` / `MINIO_ROOT_PASSWORD` / `APK_KEYSTORE_PASSWORD` 完全一致；所有密钥长度 ≥ 32 字符且为随机十六进制
- **Verification**: programmatic（diff 校验 + 长度检查）

### AC-3: MySQL 就绪后再迁移
- **Given**: 执行 `./deploy.sh up`
- **When**: MySQL 首次启动需 30 秒初始化
- **Then**: 脚本等待 MySQL ping 成功后才执行 `migrate:run`，迁移不报连接错误
- **Verification**: programmatic（检查迁移日志无 "Connection refused"）

### AC-4: 前端无 Node.js 也能构建
- **Given**: 服务器无 Node.js
- **When**: 执行 `./deploy.sh init`
- **Then**: 脚本自动用 `docker run node:20-alpine` 构建前端，`admin/dist/index.html` 存在
- **Verification**: programmatic（文件存在性检查）

### AC-5: gVisor 降级
- **Given**: 生产服务器未安装 runsc
- **When**: 执行 `./deploy.sh --prod`
- **Then**: 脚本告警"gVisor 未安装，APK 注入容器使用 seccomp 兜底"，核心平台正常启动（APK 注入容器因 runsc 缺失启动失败时，脚本提示用户运行 `./deploy.sh install-gvisor` 或暂时不启用 APK 注入）
- **Verification**: programmatic

### AC-6: APK 注入可选启用
- **Given**: 核心平台已启动
- **When**: 执行 `./deploy.sh enable-apk-inject`
- **Then**: 脚本检测 Android 工具，缺失时辅助下载 APKEditor.jar；工具就绪后启动 3 个 APK 相关服务，`curl http://localhost:8081/api/v1/health` 返回 200
- **Verification**: programmatic

### AC-7: 幂等性
- **Given**: 已成功执行过 `./deploy.sh`
- **When**: 再次执行 `./deploy.sh`
- **Then**: 不覆盖已有 .env 与 keystore，跳过已安装依赖，仅检查服务状态并按需启动
- **Verification**: programmatic（diff .env 前后一致）

### AC-8: reset 危险操作防护
- **Given**: 执行 `./deploy.sh reset` 不带 `--yes`
- **When**: 脚本提示"将删除所有数据"
- **Then**: 用户未输入确认时脚本退出，不删除任何数据
- **Verification**: programmatic

## Open Questions
- [ ] 远程 quick-start.sh 默认装在哪？`/opt/soup` 还是 `$PWD/soup`？（推荐 /opt/soup，需 sudo）
- [ ] 开发环境是否默认启用 APK 注入？（推荐否，需手动 enable-apk-inject）
- [ ] SSL 自签证书的 CN 默认取 hostname 还是要求用户传 `--domain`？（推荐 hostname + 告警）
- [ ] backup 是否自动注册 crontab？（推荐否，仅提示命令，避免未授权改系统）
- [ ] 是否提供 `./deploy.sh update` 滚动更新（git pull + 重新构建 + 滚动重启）？（推荐列入下一迭代）
