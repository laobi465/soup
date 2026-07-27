# 一键修复规范 v3（部署/容器编排层加固）

## Overview
- **Summary**: 修复第三轮全面审查确认的 6 Critical + 10 Important + 13 Minor 问题，全部集中在**部署/容器编排层**（前两轮已修业务代码层的资金/并发/鉴权问题，本轮聚焦 docker-compose、Dockerfile、nginx、redis、deploy.sh）。
- **Purpose**: 消除 admin 后台公网裸奔、MinIO/Redis 默认凭据、APK 注入容器无沙箱、密码泄露到 ps、tmpfs/volume 配置冲突等高危部署缺陷；补齐日志 rotation、healthcheck、安全响应头、资源限制等可靠性短板。
- **Target Users**: 平台运维 + 商户/代理/终端用户（间接受益于安全加固）
- **Out of Scope**: 业务代码层修复（前两轮已覆盖）、APK 注入业务逻辑、SDK 改造

## Background
- 前两轮 commit：
  - `797fe89` 修 3 Critical + 9 Important + 5 Minor（业务层）
  - `2fdc079` 修 5 Critical + 8 Important + 2 Minor（业务层）
- 本轮发现：业务代码已较完善，但**容器编排与部署脚本**仍存在 6 Critical + 10 Important + 13 Minor 新问题，需一键修复。

## Requirements

### Functional Requirements (FR)
- **FR-1 容器端口绑定加固（C1）**：dev `docker-compose.yml` 中 nginx 端口默认绑定 `127.0.0.1`，避免 admin 后台公网裸奔。
- **FR-2 凭据强制化（C2）**：MinIO `MINIO_ROOT_PASSWORD` / `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` / `MYSQL_ROOT_PASSWORD` 全部用 `${VAR:?error}` 语法，未配置时容器拒绝启动。
- **FR-3 Redis 密码与 protected-mode（C3）**：redis 启动加 `--requirepass`；`redis.conf` 改回 `protected-mode yes`；`server/.env` 与 `apk-inject-service` 配置同步密码。
- **FR-4 dev 注入容器加沙箱（C4）**：dev compose 中 `apk-inject-service` 加 `mem_limit`、`cpus`、`pids_limit`、`cap_drop: [ALL]`、`security_opt: [no-new-privileges:true]`、`read_only: true` + `tmpfs`。
- **FR-5 凭据不进 ps（C5）**：`deploy.sh` 中所有 `mysql -pPASSWORD` 改用 `MYSQL_PWD` 环境变量或 `--defaults-extra-file`。
- **FR-6 tmpfs/volume 冲突修复（C6）**：prod compose 中 `apk-inject-service` 删除 `apk-tmp:/tmp` named volume 挂载，仅保留 tmpfs；同步删除顶部 `apk-tmp` 声明。
- **FR-7 日志 rotation（I1）**：所有服务加 `logging.driver: json-file` + `max-size: 10m` + `max-file: 5`。
- **FR-8 healthcheck 与 depends_on（I2）**：mysql/redis/minio 加 healthcheck；依赖方 `depends_on` 用 `condition: service_healthy`。
- **FR-9 runsc 可选化（I3）**：prod compose 中 `runtime: runsc` 改为 `${APK_INJECT_RUNTIME:-default}`，未装 gVisor 时回退默认 runtime。
- **FR-10 dev nginx 安全头（I4）**：dev `default.conf` 补 `client_max_body_size 100M` + `X-Frame-Options SAMEORIGIN` + `X-Content-Type-Options nosniff`。
- **FR-11 compose v2 资源语法（I5）**：`mem_limit/cpus/pids_limit` 改为 `deploy.resources.limits`。
- **FR-12 Alpine mirror 可配（I6）**：PHP Dockerfile 加 `ARG ALPINE_MIRROR=dl-cdn.alpinelinux.org`，默认官方源。
- **FR-13 PHP 容器资源限制（I7）**：dev compose 中所有 PHP 容器加 `mem_limit: 512m`、`pids_limit: 128`。
- **FR-14 备份目录默认值（I8）**：`deploy.sh cmd_backup` 默认输出到 `${SCRIPT_DIR}/backups/mysql`。
- **FR-15 logs 命令增强（I9）**：`deploy.sh cmd_logs` 支持 `--since` / `--tail` / 无 `-f` 模式。
- **FR-16 seccomp syscall 补全（I10）**：`seccomp-apk-inject.json` 补 `clone3` / `openat2` / `rseq`。
- **FR-17 Minor 批量修复（M1-M13）**：见 tasks.md，逐项修复。

### Non-Functional Requirements (NFR)
- **NFR-1 兼容性**：修复后 `./deploy.sh init && ./deploy.sh up` 必须能正常启动；`./deploy.sh prod up` 必须能正常启动（含 gVisor 可选）。
- **NFR-2 向后兼容**：已有 `.env` 文件的用户升级后，新增的 `REDIS_PASSWORD` 等变量需在 `deploy.sh init` 中自动补全；缺失时打印明确错误而非崩溃。
- **NFR-3 不破坏现有功能**：所有修复点需有对应的 checklist 验证项；不能引入新的端口冲突、容器名冲突。
- **NFR-4 安全性提升**：修复后，dev 环境 admin 后台默认仅本机可访问；MinIO/Redis 必须使用强密码；APK 注入容器必须受沙箱限制。

## Acceptance Criteria
- AC-1: `docker compose -f docker-compose.yml config` 输出无 warning，所有端口绑定均为 `127.0.0.1:`。
- AC-2: 未设 `MINIO_ROOT_PASSWORD` 直接 `docker compose up` 时容器**拒绝启动**并打印明确错误。
- AC-3: `redis-cli ping` 不带密码返回 `NOAUTH Authentication required`。
- AC-4: dev `apk-inject-service` 容器 `cat /proc/1/status | grep CapEff` 显示 `0000000000000000`（cap 全部 drop）。
- AC-5: `ps aux | grep mysql` 不出现 `-p` 后跟密码的字符串。
- AC-6: prod compose 中 `apk-inject-service` 配置中无 `apk-tmp:/tmp` 挂载。
- AC-7: 所有服务 `docker inspect <svc> | jq '.[0].HostConfig.LogConfig'` 显示 `json-file` + `max-size: 10m`。
- AC-8: `docker inspect mysql --format '{{.State.Health.Status}}'` 返回 `healthy`（启动 30s 后）。
- AC-9: 未装 gVisor 时 `./deploy.sh prod up` 不报 runtime 错误，`apk-inject-service` 正常启动。
- AC-10: `curl -I http://localhost:8000` 响应头含 `X-Frame-Options: SAMEORIGIN` 与 `X-Content-Type-Options: nosniff`。
- AC-11: `./deploy.sh logs --tail 1000 nginx` 正常输出 1000 行；`./deploy.sh logs --since 10m nginx` 仅输出近 10 分钟日志。
- AC-12: `./deploy.sh backup` 在非 root 用户下成功执行（默认输出到项目目录）。
- AC-13: `seccomp-apk-inject.json` 含 `clone3`、`openat2`、`rseq` 三个 syscall。
- AC-14: 全部 Minor 项（M1-M13）已修复（见 checklist）。

## Out of Scope
- 业务代码逻辑变更（PaymentService、CardService、ApkInjectService 等业务逻辑层）
- Java 注入微服务业务代码（`apk-inject-service/src/main/java/...`）
- 前端 admin 代码
- 数据库 schema 变更

## Risks
- **R-1**：`runtime: ${APK_INJECT_RUNTIME:-default}` 在某些低版本 Compose 下可能不识别，需测试。
- **R-2**：MinIO 凭据强制化可能导致已部署用户的 `docker compose up` 失败，需在 `deploy.sh init` 中检测并补全。
- **R-3**：Redis 加密码后，PHP/Java 客户端必须同步配置，否则连接失败；需在 `server/.env`、`apk-inject-service/application.yml` 同步。
- **R-4**：dev nginx 加 `client_max_body_size 100M` 后，仍需确认 PHP `post_max_size` / `upload_max_filesize` 匹配。
