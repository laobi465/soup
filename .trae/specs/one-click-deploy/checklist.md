# 一键部署脚本 - 验证清单

## Task 1: 主框架 + doctor (FR-1)
- [ ] CP-1.1: `/workspace/deploy.sh` 存在且可执行（`chmod +x`）
- [ ] CP-1.2: shebang 为 `#!/usr/bin/env bash`，含 `set -euo pipefail`
- [ ] CP-1.3: `detect_compose()` 优先 `docker compose`，回退 `docker-compose`
- [ ] CP-1.4: `log_info/log_warn/log_error/log_step` 含时间戳与颜色
- [ ] CP-1.5: `random_hex()` 用 `openssl rand -hex`
- [ ] CP-1.6: 无子命令时默认执行 init && up
- [ ] CP-1.7: `doctor` 检查 Docker 版本 ≥ 20.10
- [ ] CP-1.8: `doctor` 检查 docker 权限（root 或 docker 组）
- [ ] CP-1.9: `doctor` 检查端口占用（dev/prod 端口集不同）
- [ ] CP-1.10: `doctor` 检查磁盘空间 ≥ 5GB
- [ ] CP-1.11: `doctor --prod` 检查 gVisor（runsc），未装输出黄色警告
- [ ] CP-1.12: 诊断报告三色区分（绿=正常/黄=警告/红=阻断）

## Task 2: init 配置生成 (FR-2)
- [ ] CP-2.1: `generate_root_env()` 生成 `.env`，权限 600
- [ ] CP-2.2: `MYSQL_ROOT_PASSWORD` 为 32 字符随机十六进制
- [ ] CP-2.3: `MINIO_ROOT_PASSWORD` 为 32 字符随机十六进制
- [ ] CP-2.4: `APK_KEYSTORE_PASSWORD` 为 32 字符随机十六进制
- [ ] CP-2.5: `generate_server_env()` 生成 `server/.env`，权限 600
- [ ] CP-2.6: `server/.env` 的 `DATABASE.PASSWORD` 与根 `.env` 的 `MYSQL_ROOT_PASSWORD` 一致
- [ ] CP-2.7: `server/.env` 的 `MINIO.SECRET_KEY` 与根 `.env` 的 `MINIO_ROOT_PASSWORD` 一致
- [ ] CP-2.8: `server/.env` 的 `APK_INJECT.KEYSTORE_PASSWORD` 与根 `.env` 的 `APK_KEYSTORE_PASSWORD` 一致
- [ ] CP-2.9: `server/.env` 的 `APP_SECRET_KEY` 为 48 字符随机十六进制
- [ ] CP-2.10: `server/.env` 的 `JWT.SECRET` 为 32 字符随机十六进制
- [ ] CP-2.11: `server/.env` 的 `QUEUE.DRIVER` = `redis`
- [ ] CP-2.12: `--prod` 时 `APP_DEBUG` = false，否则 true
- [ ] CP-2.13: `generate_keystore()` 生成 `deploy/keystore/platform.keystore`
- [ ] CP-2.14: keystore 密码用 `.env` 的 `APK_KEYSTORE_PASSWORD`
- [ ] CP-2.15: 密码写入 `deploy/keystore/.keystore-password.txt`，权限 600
- [ ] CP-2.16: 本机无 keytool 时用 Docker 容器生成
- [ ] CP-2.17: `build_frontend()` 生成 `admin/dist/index.html`
- [ ] CP-2.18: 本机无 npm 时用 `docker run node:20-alpine` 构建
- [ ] CP-2.19: `--prod` 时 `generate_ssl()` 生成 `docker/nginx/ssl/server.crt` + `server.key`
- [ ] CP-2.20: SSL 自签证书告警提示输出
- [ ] CP-2.21: init 幂等：已存在 .env/keystore/dist 时跳过（除非 `--force`）
- [ ] CP-2.22: 密钥明文不回显到终端

## Task 3: up 启动与初始化 (FR-3)
- [ ] CP-3.1: 默认不启动 APK 注入服务（apk-inject-service/queue-worker/scheduler）
- [ ] CP-3.2: `wait_mysql()` 循环 ping，超时 120 秒报错退出
- [ ] CP-3.3: `install_php_deps()` vendor 已存在时跳过
- [ ] CP-3.4: `fix_permissions()` 创建 runtime/public/uploads 并设权限
- [ ] CP-3.5: `run_migrations()` 执行 `php think migrate:run`
- [ ] CP-3.6: `run_seeds()` admin 用户已存在时跳过
- [ ] CP-3.7: 健康检查后端 `curl http://localhost:8000/`（dev）
- [ ] CP-3.8: 健康检查前端 `curl http://localhost:8080/`（dev）
- [ ] CP-3.9: 健康检查 `curl -sfk https://localhost/`（prod）
- [ ] CP-3.10: 输出访问地址表 + 默认账号 admin/admin123456
- [ ] CP-3.11: 输出"请立即修改默认密码"安全提示

## Task 4: down/status/logs/backup/reset (FR-4 ~ FR-8)
- [ ] CP-4.1: `down` 执行 `$COMPOSE down` 保留数据卷
- [ ] CP-4.2: `down --volumes` 需 `--yes` 确认
- [ ] CP-4.3: `status` 显示 `$COMPOSE ps` + 端口连通性
- [ ] CP-4.4: `logs [service]` 执行 `$COMPOSE logs --tail=100 -f`
- [ ] CP-4.5: `backup` 从 `.env` 读 `MYSQL_ROOT_PASSWORD`（不硬编码）
- [ ] CP-4.6: `backup` 输出到 `/data/backups/mysql/` 并 gzip
- [ ] CP-4.7: `backup` 清理 7 天前备份
- [ ] CP-4.8: `reset` 无 `--yes` 时退出且不删数据
- [ ] CP-4.9: `reset --yes` 执行 `$COMPOSE down -v` + 删 runtime
- [ ] CP-4.10: `reset` 后提示重新 init && up

## Task 5: enable-apk-inject (FR-9)
- [ ] CP-5.1: 检查 `zipalign`/`apksigner`/`aapt2` 在 PATH 或 `/opt/android-sdk/build-tools/`
- [ ] CP-5.2: 检查 `APKEditor.jar` 在 `apk-inject-service/tools/`
- [ ] CP-5.3: APKEditor.jar 缺失时自动 wget 下载
- [ ] CP-5.4: Android Build Tools 缺失时提示安装命令（不自动装）
- [ ] CP-5.5: 工具就绪后启动 3 个 APK 服务
- [ ] CP-5.6: 等待 `curl http://localhost:8081/api/v1/health` 返回 200
- [ ] CP-5.7: gVisor 未装时提示先运行 `install-gvisor`

## Task 6: install-gvisor + 生产降级 (FR-10, AC-5)
- [ ] CP-6.1: `install-gvisor` 检测 Debian/Ubuntu，否则报错
- [ ] CP-6.2: 按 gVisor 官方文档安装 runsc
- [ ] CP-6.3: 安装后 `sudo systemctl restart docker`
- [ ] CP-6.4: `up --prod` 未装 runsc 时输出黄色告警
- [ ] CP-6.5: 未装 runsc 时核心平台服务正常启动
- [ ] CP-6.6: enable-apk-inject 未装 runsc 时拒绝并提示 install-gvisor

## Task 7: quick-start.sh (FR-11)
- [ ] CP-7.1: `/workspace/quick-start.sh` 存在且可执行
- [ ] CP-7.2: 检测 Docker 未装时 `curl get.docker.com | sh`
- [ ] CP-7.3: 启动并 enable docker 服务
- [ ] CP-7.4: 克隆仓库到 `/opt/soup`（已存在则 git pull）
- [ ] CP-7.5: 执行 `./deploy.sh init && ./deploy.sh up`
- [ ] CP-7.6: 支持 `--prod` 与 `--branch=` 参数透传
- [ ] CP-7.7: 任一步失败时退出且打印日志
- [ ] CP-7.8: 成功后打印访问地址 + 默认账号

## Task 8: 文档与端到端验证
- [ ] CP-8.1: README.md 快速开始含 `./deploy.sh` 命令
- [ ] CP-8.2: README.md 含 `curl|bash` 远程一键命令
- [ ] CP-8.3: docs/宝塔面板部署指南.md 交叉引用 deploy.sh
- [ ] CP-8.4: `bash deploy.sh` 语法检查通过（`bash -n deploy.sh`）
- [ ] CP-8.5: `bash -n quick-start.sh` 语法检查通过
- [ ] CP-8.6: 端到端 dev 部署成功（curl 后端+前端返回 200）
- [ ] CP-8.7: 端到端 prod 部署成功（含 SSL 自签）
- [ ] CP-8.8: 幂等性：连续两次 `./deploy.sh` 第二次跳过已完成步骤
- [ ] CP-8.9: `./deploy.sh reset --yes` 后可重新部署
- [ ] CP-8.10: 代码提交并推送 GitHub main

## 验收标准覆盖核对
- [ ] AC-1 全新服务器一键部署 → Task 7 + Task 8 (CP-7.1~7.8, CP-8.6)
- [ ] AC-2 密钥自动生成与一致 → Task 2 (CP-2.2~2.10)
- [ ] AC-3 MySQL 就绪后再迁移 → Task 3 (CP-3.2, CP-3.5)
- [ ] AC-4 前端无 Node.js 也能构建 → Task 2 (CP-2.17~2.18)
- [ ] AC-5 gVisor 降级 → Task 6 (CP-6.4~6.6)
- [ ] AC-6 APK 注入可选启用 → Task 5 (CP-5.1~5.6)
- [ ] AC-7 幂等性 → Task 2 (CP-2.21) + Task 8 (CP-8.8)
- [ ] AC-8 reset 防护 → Task 4 (CP-4.8~4.9)
