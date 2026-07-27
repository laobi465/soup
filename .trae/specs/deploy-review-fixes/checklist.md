# 一键部署脚本审查修复 - 验证清单

## Task 1: env_get 空值返回非 0 (FR-1.2 / C1)
- [ ] CP-1.1: `env_get` 函数中键不存在时返回 1
- [ ] CP-1.2: `env_get` 函数中值为空字符串时返回 1
- [ ] CP-1.3: 保持对 `key="value"` 与 `key=value` 两种格式兼容
- [ ] CP-1.4: 调用方 `mysql_pwd="$(env_get MYSQL_ROOT_PASSWORD .env)" || die` 能正确触发 die
- [ ] CP-1.5: 单元测试 - 创建 .env 含 `MYSQL_ROOT_PASSWORD=abc123`，验证返回 0 且输出 abc123
- [ ] CP-1.6: 单元测试 - 删除该行后验证返回 1

## Task 2: get_compose_cmd 显式加载 override (FR-1.1 / C4)
- [ ] CP-2.1: `get_compose_cmd` 检测 docker-compose.override.yml 存在时追加 `-f docker-compose.override.yml`
- [ ] CP-2.2: dev 模式下 override 存在时输出含 `-f docker-compose.override.yml`
- [ ] CP-2.3: prod 模式下 override 存在时输出含 `-f docker-compose.override.yml`
- [ ] CP-2.4: override 不存在时输出不含该参数
- [ ] CP-2.5: 加载 override 时输出 `log_info "已加载 docker-compose.override.yml"`

## Task 3: 移除 keystore 密码明文文件 (FR-1.3 / B3)
- [ ] CP-3.1: `generate_keystore` 不再写 `.keystore-password.txt` 文件
- [ ] CP-3.2: 改为 `log_info` 提示"密码请查阅 .env 的 APK_KEYSTORE_PASSWORD"
- [ ] CP-3.3: 删除工作树中已存在的 `deploy/keystore/.keystore-password.txt`
- [ ] CP-3.4: .gitignore 增加 `*.keystore-password.txt` 规则
- [ ] CP-3.5: 执行 `./deploy.sh init --force` 后 `test ! -f deploy/keystore/.keystore-password.txt`

## Task 4: run_seeds 检测逻辑 (FR-2.4 / C6 - 部分)
- [ ] CP-4.1: 检测 admin 用户存在则跳过 UserSeeder（而非整个 seed:run）
- [ ] CP-4.2: admin 不存在时执行 `php think seed:run -s UserSeeder`
- [ ] CP-4.3: 检测 ca_packages 为空时执行 PackageSeeder
- [ ] CP-4.4: 检测 ca_system_configs 为空时执行 SystemConfigSeeder
- [ ] CP-4.5: 检测 ca_admin_menus 为空时执行 AdminMenuSeeder
- [ ] CP-4.6: SQL 查询失败时返回 1 并 log_error（不再静默返回 0）
- [ ] CP-4.7: 各 seeder 失败时 log_warn 但不中断整体流程

## Task 5: UserSeeder 幂等性 (FR-2.4 / C6 - 部分)
- [ ] CP-5.1: 使用 `onConflict(['id'])->replace()` 实现 upsert
- [ ] CP-5.2: 重复执行 `php think seed:run -s UserSeeder` 不报主键冲突
- [ ] CP-5.3: admin 用户的 password_hash 会被更新为最新值
- [ ] CP-5.4: PHP 语法检查 `php -l UserSeeder.php` 通过

## Task 6: install_php_deps 完整性检测 (FR-2.3 / C5)
- [ ] CP-6.1: 检测 `vendor/autoload.php` 与 `vendor/composer/installed.json` 同时存在
- [ ] CP-6.2: 任一缺失则视为不完整
- [ ] CP-6.3: 不完整时清理残缺 vendor（`rm -rf vendor composer.lock`）
- [ ] CP-6.4: 清理后执行 composer install
- [ ] CP-6.5: 完整时跳过并 log_info "vendor 完整, 跳过"

## Task 7: random_hex 兜底 + doctor 检测 (FR-2.5 / C7)
- [ ] CP-7.1: `random_hex` 优先使用 `openssl rand -hex`
- [ ] CP-7.2: openssl 不可用时用 `/dev/urandom` 兜底
- [ ] CP-7.3: 两者均不可用时 `die` 退出
- [ ] CP-7.4: `cmd_doctor` 新增 openssl 检测项
- [ ] CP-7.5: openssl 已安装时 log_ok 显示版本
- [ ] CP-7.6: openssl 未安装时 log_warn 提示回退

## Task 8: override 变化时强制 recreate (FR-2.8 / F1)
- [ ] CP-8.1: `handle_port_conflicts` 生成 override 时设置 `FORCE_RECREATE=true`
- [ ] CP-8.2: `handle_port_conflicts` 更新 override 时设置 `FORCE_RECREATE=true`
- [ ] CP-8.3: `start_services` 检测 `FORCE_RECREATE=true` 追加 `--force-recreate`
- [ ] CP-8.4: `cmd_up` 开头重置 `FORCE_RECREATE=false`
- [ ] CP-8.5: 无 override 变化时不追加 `--force-recreate`

## Task 9: docker run 后修复属主 (FR-2.9 / F5/F6)
- [ ] CP-9.1: `build_frontend` 后 chown 修正 admin/dist 属主
- [ ] CP-9.2: `build_frontend` 后 chown 修正 admin/node_modules 属主
- [ ] CP-9.3: `generate_keystore` 后 chown 修正 keystore 文件属主
- [ ] CP-9.4: `generate_keystore` 后 chmod 600 keystore 文件
- [ ] CP-9.5: chown 失败时不中断脚本（用 `|| true`）

## Task 10: dev→prod 模式切换警告 (FR-2.6 / D1)
- [ ] CP-10.1: `cmd_init` 检测当前 server/.env 的 APP_DEBUG 值
- [ ] CP-10.2: dev→prod 切换时输出警告
- [ ] CP-10.3: prod→dev 切换时输出警告
- [ ] CP-10.4: 非 `--yes` 模式下要求用户确认
- [ ] CP-10.5: 用户拒绝时 exit 0
- [ ] CP-10.6: 首次部署（无 server/.env）时不触发警告

## Task 11: macOS hostname 兼容 (FR-2.7 / E1)
- [ ] CP-11.1: macOS 下用 `ipconfig getifaddr en0`
- [ ] CP-11.2: en0 失败时尝试 en1
- [ ] CP-11.3: 均失败时回退 localhost
- [ ] CP-11.4: Linux 下保持 `hostname -I`
- [ ] CP-11.5: Linux 下 hostname -I 失败时回退 localhost
- [ ] CP-11.6: 修复 doctor 中 df 分支冗余（E2）

## Task 12: backup.sh 移除弱密码 (FR-2.10 / G4)
- [ ] CP-12.1: `MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD:?必须设置 MYSQL_ROOT_PASSWORD 环境变量}"`
- [ ] CP-12.2: 移除 `:-root123456` 兜底
- [ ] CP-12.3: 未设置环境变量时脚本退出并提示
- [ ] CP-12.4: `cmd_backup` 备份目录 `chmod 700`（FR-3.1 / H1）

## Task 13: quick-start.sh 移除 sudo -E (FR-2.1 / B6)
- [ ] CP-13.1: `sudo -E ./deploy.sh init` 改为 `sudo ./deploy.sh init`
- [ ] CP-13.2: `sudo -E ./deploy.sh up` 改为 `sudo ./deploy.sh up`
- [ ] CP-13.3: `grep "sudo -E" quick-start.sh` 无输出

## Task 14: install-gvisor 安全加固 (FR-2.2 / B7)
- [ ] CP-14.1: sudo bash -c 块内首行加 `set -euo pipefail`
- [ ] CP-14.2: `gpg --dearmor` 加 `--yes` 避免交互
- [ ] CP-14.3: 安装前 `log_warn` 提示"将重启 Docker daemon"
- [ ] CP-14.4: 非 `--yes` 模式下要求用户确认
- [ ] CP-14.5: curl 失败时 set -e 触发中断

## Task 15: Minor 修复集合 (FR-3.1~3.5)
- [ ] CP-15.1: cmd_status 端口检查标注"宿主机端口连通性"
- [ ] CP-15.2: cmd_down 用循环遍历 EXTRA_ARGS 检测 --volumes
- [ ] CP-15.3: jwt_secret 改为 `random_hex 32`（64 字符）
- [ ] CP-15.4: .gitignore 增加 `*.sql`
- [ ] CP-15.5: .gitignore 增加 `*.sql.gz`
- [ ] CP-15.6: .gitignore 增加 `*.pem`

## Task 16: README 更新 (G2/G4/G5)
- [ ] CP-16.1: 端口说明标注"127.0.0.1:3306（仅本机可访问）"
- [ ] CP-16.2: 手动备份命令改为 `docker compose exec mysql bash /workspace/scripts/backup.sh`
- [ ] CP-16.3: 说明需从 .env 导出 MYSQL_ROOT_PASSWORD
- [ ] CP-16.4: 数据库表数量更新（追加 ca_apk_inject_tasks）

## Task 17: 语法验证 + 提交推送
- [ ] CP-17.1: `bash -n deploy.sh` 通过
- [ ] CP-17.2: `bash -n quick-start.sh` 通过
- [ ] CP-17.3: `php -l server/database/seeds/UserSeeder.php` 通过
- [ ] CP-17.4: `./deploy.sh doctor` 实测 openssl 检测项正常显示
- [ ] CP-17.5: git commit message 详细列出修复项
- [ ] CP-17.6: git push origin main 成功

---

## 端到端验证（可选，需真实 Docker 环境）
- [ ] E2E-1: 干净环境执行 `./deploy.sh` 完成 dev 部署，访问 http://localhost:8000 正常
- [ ] E2E-2: 重复执行 `./deploy.sh up` 不报错（幂等性）
- [ ] E2E-3: 占用 6379 端口后执行 `./deploy.sh up`，自动生成 override 并启动成功
- [ ] E2E-4: 删除 vendor/autoload.php 后执行 `./deploy.sh up`，自动重装
- [ ] E2E-5: 重复执行 `./deploy.sh init --force`，UserSeeder 不报主键冲突
