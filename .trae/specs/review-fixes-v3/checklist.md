# 一键修复 v3 验证清单

## Task 1: dev compose 端口绑定 127.0.0.1 (FR-1 / C1)
- [ ] CP-1.1: docker-compose.yml 中 nginx.ports 全部含 `127.0.0.1:` 前缀
- [ ] CP-1.2: `docker compose -f docker-compose.yml config | grep -A3 'ports:'` 不出现 `0.0.0.0` 或裸 `8000:80`

## Task 2: MinIO/MySQL 凭据强制化 (FR-2 / C2)
- [ ] CP-2.1: docker-compose.yml 中 `MINIO_ROOT_PASSWORD` 使用 `${VAR:?error}` 语法
- [ ] CP-2.2: docker-compose.prod.yml 同步修改
- [ ] CP-2.3: apk-inject-service 的 `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` 同步修改
- [ ] CP-2.4: deploy.sh cmd_init 生成 `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` 写入 .env
- [ ] CP-2.5: 未设环境变量 `docker compose up` 报 `MINIO_ROOT_PASSWORD is required`

## Task 3: Redis 加密码 + protected-mode (FR-3 / C3)
- [ ] CP-3.1: docker/redis/redis.conf 中 `protected-mode yes`
- [ ] CP-3.2: docker-compose.yml 中 redis `command` 含 `--requirepass "${REDIS_PASSWORD:?...}"`
- [ ] CP-3.3: docker-compose.prod.yml 同步修改
- [ ] CP-3.4: deploy.sh cmd_init 生成 `REDIS_PASSWORD` 并写入 .env 与 server/.env
- [ ] CP-3.5: apk-inject-service/application.yml 中 Redis 配置含密码
- [ ] CP-3.6: `docker exec card-auth-redis redis-cli ping` 返回 `NOAUTH Authentication required`

## Task 4: dev 注入容器加沙箱 (FR-4 / C4)
- [ ] CP-4.1: docker-compose.yml 中 apk-inject-service 含 `mem_limit: 1g`
- [ ] CP-4.2: 含 `cpus: 1`
- [ ] CP-4.3: 含 `pids_limit: 64`
- [ ] CP-4.4: 含 `cap_drop: [ALL]`
- [ ] CP-4.5: 含 `security_opt: [no-new-privileges:true]`
- [ ] CP-4.6: 含 `read_only: true`
- [ ] CP-4.7: 含 `tmpfs: [/tmp:noexec,nosuid,nodev,size=1g]`
- [ ] CP-4.8: `docker inspect card-auth-apk-inject --format '{{.HostConfig.Capdrop}}'` 显示 `[ALL]`

## Task 5: 凭据不进 ps (FR-5 / C5)
- [ ] CP-5.1: deploy.sh 中所有 `mysql -uroot -p"..."` 改为 `MYSQL_PWD="..." mysql -uroot ...`
- [ ] CP-5.2: 所有 `mysqldump -uroot -p"..."` 改为 `MYSQL_PWD="..." mysqldump -uroot ...`
- [ ] CP-5.3: 所有 `mysqladmin -uroot -p"..."` 改为 `MYSQL_PWD="..." mysqladmin -uroot ...`
- [ ] CP-5.4: `grep -rn ' -p"' deploy.sh` 无匹配

## Task 6: prod compose tmpfs/volume 冲突修复 (FR-6 / C6)
- [ ] CP-6.1: docker-compose.prod.yml 中 apk-inject-service.volumes 不含 `apk-tmp:/tmp`
- [ ] CP-6.2: 顶部 `volumes:` 声明中不含 `apk-tmp`
- [ ] CP-6.3: 保留 `tmpfs: [/tmp:noexec,nosuid,nodev,size=2g]`
- [ ] CP-6.4: 保留 `- ./deploy/keystore:/opt/keystore:ro`

## Task 7: 日志 rotation (FR-7 / I1)
- [ ] CP-7.1: docker-compose.yml 所有服务含 `logging.driver: json-file`
- [ ] CP-7.2: 含 `max-size: "10m"`
- [ ] CP-7.3: 含 `max-file: "5"`
- [ ] CP-7.4: docker-compose.prod.yml 同步修改
- [ ] CP-7.5: `docker inspect card-auth-nginx --format '{{.HostConfig.LogConfig.Config}}'` 含 `max-size:10m`

## Task 8: healthcheck 与 depends_on (FR-8 / I2)
- [ ] CP-8.1: mysql 服务含 healthcheck 配置
- [ ] CP-8.2: redis 服务含 healthcheck 配置（带密码）
- [ ] CP-8.3: minio 服务含 healthcheck 配置
- [ ] CP-8.4: php-fpm `depends_on` 用 `condition: service_healthy`
- [ ] CP-8.5: apk-queue-worker / apk-scheduler / apk-inject-service 同步
- [ ] CP-8.6: docker-compose.prod.yml 同步修改
- [ ] CP-8.7: 启动 30s 后 `docker inspect card-auth-mysql --format '{{.State.Health.Status}}'` 为 `healthy`

## Task 9: runsc 可选化 (FR-9 / I3)
- [ ] CP-9.1: docker-compose.prod.yml 中 `runtime: ${APK_INJECT_RUNTIME:-default}` 或运行时动态注入
- [ ] CP-9.2: deploy.sh cmd_up 检测 gVisor 安装情况
- [ ] CP-9.3: 未装 gVisor 时 log_warn 提示但不阻断
- [ ] CP-9.4: 未装 gVisor 时 `./deploy.sh prod up` 不报错

## Task 10: dev nginx 安全头 (FR-10 / I4)
- [ ] CP-10.1: docker/nginx/default.conf 含 `client_max_body_size 100M`
- [ ] CP-10.2: 含 `add_header X-Frame-Options "SAMEORIGIN" always;`
- [ ] CP-10.3: 含 `add_header X-Content-Type-Options "nosniff" always;`
- [ ] CP-10.4: 含 `add_header Referrer-Policy "strict-origin-when-cross-origin" always;`
- [ ] CP-10.5: 8080 server 块同步添加
- [ ] CP-10.6: `docker exec card-auth-nginx nginx -t` 通过

## Task 11: compose v2 资源语法 (FR-11 / I5)
- [ ] CP-11.1: docker-compose.prod.yml 中 `mem_limit` 改为 `deploy.resources.limits.memory`
- [ ] CP-11.2: `cpus` 改为 `deploy.resources.limits.cpus`
- [ ] CP-11.3: `pids_limit` 改为 `deploy.resources.limits.pids`
- [ ] CP-11.4: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config` 无 warning

## Task 12: Alpine mirror 可配 (FR-12 / I6)
- [ ] CP-12.1: docker/php/Dockerfile 含 `ARG ALPINE_MIRROR=dl-cdn.alpinelinux.org`
- [ ] CP-12.2: 默认构建走官方源
- [ ] CP-12.3: `--build-arg ALPINE_MIRROR=mirrors.aliyun.com` 时走阿里云

## Task 13: PHP 容器资源限制 (FR-13 / I7)
- [ ] CP-13.1: docker-compose.yml 中 php-fpm 含 `mem_limit: 512m`
- [ ] CP-13.2: php-fpm 含 `pids_limit: 128`
- [ ] CP-13.3: apk-queue-worker 含 `mem_limit: 512m`
- [ ] CP-13.4: apk-scheduler 含 `mem_limit: 256m`

## Task 14: 备份目录默认值 (FR-14 / I8)
- [ ] CP-14.1: deploy.sh cmd_backup 默认输出到 `${SCRIPT_DIR}/backups/mysql`
- [ ] CP-14.2: 非根用户 `./deploy.sh backup` 成功执行

## Task 15: logs 命令增强 (FR-15 / I9)
- [ ] CP-15.1: 支持 `--tail N` 参数
- [ ] CP-15.2: 支持 `--since TIME` 参数
- [ ] CP-15.3: 支持 `--no-follow` / `-n` 参数
- [ ] CP-15.4: `./deploy.sh logs --tail 1000 nginx` 输出 1000 行后退出
- [ ] CP-15.5: `./deploy.sh logs --since 10m nginx` 仅输出近 10 分钟日志

## Task 16: seccomp syscall 补全 (FR-16 / I10)
- [ ] CP-16.1: deploy/seccomp-apk-inject.json 含 `clone3`
- [ ] CP-16.2: 含 `openat2`
- [ ] CP-16.3: 含 `rseq`
- [ ] CP-16.4: `jq . deploy/seccomp-apk-inject.json` 通过

## Task 17: Minor 批量修复 (FR-17 / M1-M13)
- [ ] CP-17.1 (M1): deploy.sh cmd_init 不打印明文密码
- [ ] CP-17.2 (M2): deploy.sh env_get 用 awk 精确匹配
- [ ] CP-17.3 (M3): cmd_install_gvisor warning 文案改为"影响宿主机所有容器"
- [ ] CP-17.4 (M4): health_check 校验响应体（含 `"code":0` 或 `ok`）
- [ ] CP-17.5 (M5): docker/php/Dockerfile 移除 mysqli
- [ ] CP-17.6 (M6): nginx.prod.conf 移除 X-XSS-Protection
- [ ] CP-17.7 (M7): apk-inject-service/Dockerfile 移除 bash 或 entrypoint 用 bash
- [ ] CP-17.8 (M8): deploy.sh cmd_reset 增加 `rm -rf server/public/uploads/`
- [ ] CP-17.9 (M9): minio-init 抽取（如可行）
- [ ] CP-17.10 (M10): deploy/keystore/.gitkeep 保留且 .gitignore 含 `!.gitkeep`
- [ ] CP-17.11 (M11): cmd_enable_apk_inject 端口不硬编码
- [ ] CP-17.12 (M12): application.yml 添加 APK_WORK_DIR（可选）
- [ ] CP-17.13 (M13): nginx ulimits 补 nproc

## Task 18: 语法验证 + 部署测试
- [ ] CP-18.1: `docker compose -f docker-compose.yml config` 无错误
- [ ] CP-18.2: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config` 无错误
- [ ] CP-18.3: `bash -n deploy.sh` 通过
- [ ] CP-18.4: `bash -n quick-start.sh` 通过
- [ ] CP-18.5: `jq . deploy/seccomp-apk-inject.json` 通过
- [ ] CP-18.6: git commit + push 到 main
