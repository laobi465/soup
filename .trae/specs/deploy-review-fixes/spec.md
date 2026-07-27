# 一键部署脚本审查修复 - PRD

## Overview
- **Summary**: 修复 `/review` 全面审查发现的 3 Critical + 9 Important + 关键 Minor 问题，覆盖 deploy.sh / quick-start.sh / scripts/backup.sh / UserSeeder.php / .gitignore / README.md。
- **Purpose**: 消除部署失败、密钥泄露、幂等性破坏、跨平台兼容性等高风险缺陷，确保一键部署脚本在 prod/dev 模式下均稳定可用。
- **Target Users**: 平台运维、开发者、商户自部署

## Background & Context
- 一键部署脚本 (`deploy.sh` / `quick-start.sh`) 已完成 MVP 并推送 main，但在真实服务器部署时连续暴露 3 个阻断性问题（`--scale 0` 报错、端口冲突、composer 权限、.env 解析括号），已临时修复。
- 随后进行的 `/review` 全面审查发现 23 个问题，其中 3 个 Critical 会导致 prod 模式端口冲突处理失效、空密码被静默使用、keystore 密码明文存储。
- 本规范聚焦修复 Critical 与 Important 问题，并包含影响实际使用的 Minor 项。

## Goals
- 修复 3 个 Critical 问题，消除 prod 模式部署失败与密钥静默失效风险
- 修复 9 个 Important 问题，覆盖幂等性、跨平台、安全性、错误处理
- 修复影响实际使用的 Minor 问题（backup 目录权限、端口检查语义、--volumes 解析）
- 保持现有功能不变，不引入新特性

## Non-Goals (Out of Scope)
- 重构 deploy.sh 架构（保持单文件结构）
- 新增子命令或功能（如 `update` / `--clean-override`）
- Kubernetes / Helm 部署
- 性能优化（当前执行时长可接受）
- 完整重写 UserSeeder（仅做幂等性最小修复）

## Functional Requirements

### FR-1: Critical 修复

#### FR-1.1: prod 模式显式加载 override 文件 (C4)
- `get_compose_cmd()` 检测 `docker-compose.override.yml` 存在时，追加 `-f docker-compose.override.yml`
- 适用于 dev 与 prod 模式（prod 模式下 compose v2 不会自动加载 override，必须显式 `-f`）
- 加载时输出 `log_info` 提示

#### FR-1.2: `env_get` 键不存在时返回非 0 (C1)
- 键不存在或值为空时，函数返回非 0
- 调用方 `|| die` 能正确触发，避免空密码被静默使用
- 保持对 `key="value"` 与 `key=value` 两种格式的兼容

#### FR-1.3: 移除 keystore 密码明文文件 (B3)
- `generate_keystore()` 不再将密码写入 `.keystore-password.txt`
- 改为提示用户从 `.env` 的 `APK_KEYSTORE_PASSWORD` 查阅
- 删除工作树中已存在的 `deploy/keystore/.keystore-password.txt`
- `.gitignore` 增加防御性规则 `*.keystore-password.txt`

### FR-2: Important 修复

#### FR-2.1: quick-start.sh 移除 `sudo -E` (B6)
- 改为 `sudo ./deploy.sh ...`（不传 `-E`）
- deploy.sh 所有配置自行生成，不需要外部环境变量

#### FR-2.2: install-gvisor 添加 `set -e` 与重启警告 (B7)
- `sudo bash -c` 块内首行加 `set -euo pipefail`
- `gpg --dearmor` 加 `--yes` 避免交互
- 安装前 `log_warn` 提示"将重启 Docker daemon，所有运行中容器会被中断"
- 非 `--yes` 模式下要求用户确认

#### FR-2.3: install_php_deps 检测 vendor 完整性 (C5)
- 检测 `vendor/autoload.php` 与 `vendor/composer/installed.json` 同时存在
- 任一缺失则视为不完整，强制重装
- 重装前清理残缺 vendor（避免 composer 混乱）

#### FR-2.4: UserSeeder 幂等性修复 (C6)
- 改用 `ON DUPLICATE KEY UPDATE` 或先检测后插入
- `run_seeds` 改进：分别检测各 seeder 目标（admin 用户、基础套餐、系统配置、菜单）
- 任一缺失则执行对应 seeder（用 `php think seed:run -s XxxSeeder`）

#### FR-2.5: random_hex 兜底 + doctor 检测 openssl (C7)
- `random_hex` 优先 `openssl rand -hex`，失败时用 `/dev/urandom` 兜底
- `cmd_doctor` 新增 openssl 检测项

#### FR-2.6: dev→prod 模式切换警告 (D1)
- `cmd_init` 检测当前 `server/.env` 的 `APP_DEBUG` 值
- 若从 dev 切换到 prod（或反向），输出警告"将重新生成密钥，数据卷可能无法访问"
- 非 `--yes` 模式下要求用户确认
- `--force` 时才真正重新生成

#### FR-2.7: macOS hostname 兼容 (E1)
- `print_access_info` 在 macOS 下用 `ipconfig getifaddr en0`
- Linux 下保持 `hostname -I`
- 失败时回退 `localhost`

#### FR-2.8: override 变化时强制 recreate (F1)
- `handle_port_conflicts` 生成/更新 override 时设置 `FORCE_RECREATE=true`
- `start_services` 检测该标志，追加 `--force-recreate`

#### FR-2.9: docker run 文件属主修复 (F5/F6)
- `build_frontend` 用 docker run 后，`chown` 修正 `admin/dist` 与 `admin/node_modules` 属主
- `generate_keystore` 用 docker run 后，`chown` 修正 keystore 文件属主并 `chmod 600`

#### FR-2.10: scripts/backup.sh 移除默认弱密码 (G4)
- `MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD:?必须设置 MYSQL_ROOT_PASSWORD 环境变量}"`
- 移除 `:-root123456` 兜底
- README 更正手动备份执行方式

### FR-3: Minor 修复

#### FR-3.1: cmd_backup 备份目录权限 (H1)
- `mkdir -p "$backup_dir" && chmod 700 "$backup_dir"`

#### FR-3.2: cmd_status 端口检查语义 (H3)
- 明确标注"宿主机端口连通性（若启用了端口冲突 override，mysql/redis 可能不监听宿主机）"

#### FR-3.3: cmd_down --volumes 解析 (F4)
- 用循环遍历 `EXTRA_ARGS`，不限于前两个位置

#### FR-3.4: JWT secret 长度 (B1)
- `jwt_secret=$(random_hex 32)`（64 字符）

#### FR-3.5: .gitignore 补充 (B5)
- 增加 `*.sql` / `*.sql.gz` / `*.pem` / `*.keystore-password.txt`

## Non-Functional Requirements
- **幂等性**: 所有修复不得破坏现有幂等性，重复执行 `init`/`up` 仍安全
- **向后兼容**: 不改变现有命令行接口与 .env 字段结构
- **跨平台**: 修复后 macOS 与 Linux 均可运行
- **不引入新依赖**: 仅用 bash 内置 + openssl + docker
- **测试验证**: 每个 Critical 修复需有可验证的测试方法

## Constraints
- 不修改 `docker-compose.yml` / `docker-compose.prod.yml` 的服务定义
- 不改变 `server/.env` 的字段结构（仅改注释与取值）
- 保持单文件 deploy.sh（不拆分为多文件）
- UserSeeder 修复需兼容 Phinx migration 的 `onConflict` 语法

## Acceptance Criteria

### AC-1: prod 模式端口冲突处理生效
- **Given**: prod 模式，宿主机 6379 被外部 Redis 占用
- **When**: 执行 `./deploy.sh --prod`
- **Then**: `handle_port_conflicts` 生成 override，`get_compose_cmd` 显式加载 override，redis 容器不绑定宿主机端口，启动成功
- **Verification**: programmatic（检查 compose 命令含 `-f docker-compose.override.yml`，redis 容器端口映射为空）

### AC-2: env_get 空值不再被静默使用
- **Given**: `.env` 中 `MYSQL_ROOT_PASSWORD` 行被删除
- **When**: 执行 `./deploy.sh init` 触发 `generate_server_env`
- **Then**: `env_get MYSQL_ROOT_PASSWORD .env` 返回非 0，`die` 触发，脚本退出并提示"无法读取 MYSQL_ROOT_PASSWORD"
- **Verification**: programmatic（exit code 非 0，stderr 含错误信息）

### AC-3: keystore 密码不再明文存储
- **Given**: 执行 `./deploy.sh init`
- **When**: `generate_keystore` 运行
- **Then**: 不生成 `.keystore-password.txt` 文件，仅提示"密码请查阅 .env 的 APK_KEYSTORE_PASSWORD"
- **Verification**: programmatic（`test ! -f deploy/keystore/.keystore-password.txt`）

### AC-4: UserSeeder 幂等
- **Given**: `ca_users` 表已有 admin 用户
- **When**: 重复执行 `php think seed:run -s UserSeeder`
- **Then**: 不报主键冲突错误，admin 用户信息保持不变或更新
- **Verification**: programmatic（exit code 0）

### AC-5: macOS 下访问地址正确
- **Given**: macOS 环境
- **When**: 执行 `./deploy.sh up`
- **Then**: `print_access_info` 输出 `http://<实际IP>:8000/` 而非 `http://:8000/`
- **Verification**: programmatic（grep 输出不含 `http://:`）

### AC-6: backup.sh 不再用弱密码
- **Given**: 未设置 `MYSQL_ROOT_PASSWORD` 环境变量
- **When**: 执行 `bash scripts/backup.sh`
- **Then**: 脚本退出并提示"必须设置 MYSQL_ROOT_PASSWORD"，不尝试用 root123456 连接
- **Verification**: programmatic（exit code 非 0）

### AC-7: install-gvisor 失败时中断
- **Given**: 网络故障导致 `curl` 拉取 GPG key 失败
- **When**: 执行 `./deploy.sh install-gvisor`
- **Then**: `set -e` 触发，脚本中断，不继续 `apt-get install`
- **Verification**: programmatic（exit code 非 0，未执行 apt-get install）

### AC-8: vendor 完整性检测
- **Given**: `vendor/` 目录存在但 `vendor/autoload.php` 缺失（半成品）
- **When**: 执行 `./deploy.sh up`
- **Then**: `install_php_deps` 检测到不完整，清理残缺 vendor 并重新安装
- **Verification**: programmatic（composer install 被执行）

## Open Questions
- [ ] UserSeeder 幂等修复用 `onConflict`（Phinx 支持）还是先 `DELETE WHERE id=1` 再 insert？（推荐 onConflict，更安全）
- [ ] `run_seeds` 分别检测各 seeder 目标是否过度复杂？（推荐保留全局检测，但 UserSeeder 幂等后整体 seed:run 也安全）
- [ ] macOS `ipconfig getifaddr en0` 在无线网络（en1）下是否失效？（推荐尝试 en0 失败回退 localhost）
