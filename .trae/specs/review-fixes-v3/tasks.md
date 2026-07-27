# 一键修复 v3 任务清单

## [ ] Task 1: dev compose 端口绑定 127.0.0.1 (FR-1 / C1)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml)
  - `nginx.ports` 中 `"8000:80"` 改为 `"127.0.0.1:8000:80"`
  - `"8080:8080"` 改为 `"127.0.0.1:8080:8080"`
  - 其他服务（mysql/redis/minio）已是 `127.0.0.1:` 前缀，无需改动
  - 修改要点：
    ```yaml
    nginx:
      ports:
        - "127.0.0.1:8000:80"
        - "127.0.0.1:8080:8080"
    ```
  - 验证：`docker compose -f docker-compose.yml config | grep -A2 ports` 全部含 `127.0.0.1`

## [ ] Task 2: MinIO/MySQL 凭据强制化 (FR-2 / C2)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml) 和 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml)
  - 所有 `${MINIO_ROOT_PASSWORD:-minioadmin123}` 改为 `${MINIO_ROOT_PASSWORD:?MINIO_ROOT_PASSWORD is required}`
  - 所有 `${MINIO_ROOT_USER:-minioadmin}` 改为 `${MINIO_ROOT_USER:?MINIO_ROOT_USER is required}`
  - `apk-inject-service` 的 `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` 同样用 `:?` 强制
  - MySQL 已是 `:?` 模式，确认无需改动
  - 同步 [deploy.sh](file:///workspace/deploy.sh) 中 `cmd_init` 必须生成 `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` 并写入 `.env`（若已有则跳过）
  - 验证：未设环境变量直接 `docker compose up` 时报错 `MINIO_ROOT_PASSWORD is required`

## [ ] Task 3: Redis 加密码 + protected-mode (FR-3 / C3)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [docker/redis/redis.conf](file:///workspace/docker/redis/redis.conf)：
    - `protected-mode no` 改为 `protected-mode yes`
    - 移除 `bind 0.0.0.0`（让 redis 仅监听容器内）或保留但确保有密码
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml) 和 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml)：
    - redis 服务 `command` 加 `--requirepass "${REDIS_PASSWORD:?REDIS_PASSWORD is required}"`
  - 修改 [deploy.sh](file:///workspace/deploy.sh) `cmd_init`：
    - 生成强随机 `REDIS_PASSWORD`，写入 `.env` 和 `server/.env`（`[REDIS] PASSWORD =`）
  - 修改 [server/config/cache.php](file:///workspace/server/config/cache.php) 或确认 ThinkPHP Redis 配置能从 env 读取密码
  - 修改 [apk-inject-service/src/main/resources/application.yml](file:///workspace/apk-inject-service/src/main/resources/application.yml) 同步 Redis 密码
  - 验证：`docker exec card-auth-redis redis-cli ping` 返回 `NOAUTH Authentication required`

## [ ] Task 4: dev 注入容器加沙箱 (FR-4 / C4)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml) 中 `apk-inject-service`：
    - 添加 `mem_limit: 1g`
    - 添加 `cpus: 1`
    - 添加 `pids_limit: 64`
    - 添加 `cap_drop: [ALL]`
    - 添加 `security_opt: [no-new-privileges:true]`
    - 添加 `read_only: true`
    - 添加 `tmpfs: [/tmp:noexec,nosuid,nodev,size=1g]`
  - 验证：`docker inspect card-auth-apk-inject --format '{{.HostConfig.Capdrop}}'` 显示 `[ALL]`

## [ ] Task 5: 凭据不进 ps (FR-5 / C5)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh](file:///workspace/deploy.sh)：
    - 所有 `mysql -uroot -p"${mysql_pwd}"` 改为 `MYSQL_PWD="${mysql_pwd}" mysql -uroot ...`
    - 所有 `mysqldump -uroot -p"${mysql_pwd}"` 改为 `MYSQL_PWD="${mysql_pwd}" mysqldump -uroot ...`
    - 所有 `mysqladmin -uroot -p"${mysql_pwd}"` 同样改用 `MYSQL_PWD`
  - 涉及行：L600、L676、L678、L1078-1086（grep `mysql -u` 与 `mysqldump -u` 全部确认）
  - 验证：`ps aux | grep mysql | grep -v grep` 不出现 `-p` 后跟字符串

## [ ] Task 6: prod compose tmpfs/volume 冲突修复 (FR-6 / C6)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml)：
    - `apk-inject-service.volumes` 中移除 `- apk-tmp:/tmp`
    - 保留 `- ./deploy/keystore:/opt/keystore:ro`
    - 保留 `tmpfs: [/tmp:noexec,nosuid,nodev,size=2g]`（size 从 1g 调到 2g，处理大 APK）
    - 顶部 `volumes:` 声明中移除 `apk-tmp`
  - 验证：`docker compose -f docker-compose.yml -f docker-compose.prod.yml config | grep apk-tmp` 无输出

## [ ] Task 7: 日志 rotation (FR-7 / I1)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml) 和 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml)：
    - 所有服务（nginx/php-fpm/mysql/redis/minio/minio-init/apk-inject-service/apk-queue-worker/apk-scheduler）添加：
      ```yaml
      logging:
        driver: json-file
        options:
          max-size: "10m"
          max-file: "5"
      ```
    - 用 YAML anchor `x-logging: &default-logging` 减少重复
  - 验证：`docker inspect card-auth-nginx --format '{{.HostConfig.LogConfig.Config}}'` 含 `max-size:10m`

## [ ] Task 8: healthcheck 与 depends_on (FR-8 / I2)
- **Priority**: important
- **Depends On**: Task 3
- **Description**:
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml) 和 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml)：
    - mysql 加：
      ```yaml
      healthcheck:
        test: ["CMD-SHELL", "mysqladmin ping -h localhost -uroot -p$$MYSQL_ROOT_PASSWORD --silent"]
        interval: 10s
        timeout: 5s
        retries: 10
        start_period: 30s
      ```
    - redis 加（密码模式）：
      ```yaml
      healthcheck:
        test: ["CMD", "redis-cli", "-a", "$$REDIS_PASSWORD", "ping"]
        interval: 10s
        timeout: 5s
        retries: 5
      ```
    - minio 加：
      ```yaml
      healthcheck:
        test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
        interval: 15s
        timeout: 5s
        retries: 5
      ```
    - php-fpm `depends_on` 改为：
      ```yaml
      depends_on:
        mysql:
          condition: service_healthy
        redis:
          condition: service_healthy
      ```
    - apk-queue-worker / apk-scheduler / apk-inject-service 同步加 `condition: service_healthy`
  - 验证：`docker inspect card-auth-mysql --format '{{.State.Health.Status}}'` 启动 30s 后为 `healthy`

## [ ] Task 9: runsc 可选化 (FR-9 / I3)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml) 中 `apk-inject-service`：
    - `runtime: runsc` 改为 `runtime: ${APK_INJECT_RUNTIME:-default}`
    - 注意：`default` 在某些 compose 版本会被当作 runtime 名，需测试；若不识别则移除 `runtime` 字段，改用 deploy.sh 在 cmd_up 时动态注入
  - 修改 [deploy.sh](file:///workspace/deploy.sh)：
    - `cmd_up` 中检测 gVisor 是否安装，若安装则设置 `export APK_INJECT_RUNTIME=runsc`，否则不设置
    - prod 模式下若未装 gVisor，log_warn 提示"未启用 gVisor 沙箱，APK 注入将仅由 seccomp 限制"
  - 验证：未装 gVisor 时 `./deploy.sh prod up` 不报错

## [ ] Task 10: dev nginx 安全头 (FR-10 / I4)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [docker/nginx/default.conf](file:///workspace/docker/nginx/default.conf)：
    - 在 server 块顶部添加：
      ```nginx
      client_max_body_size 100M;
      add_header X-Frame-Options "SAMEORIGIN" always;
      add_header X-Content-Type-Options "nosniff" always;
      add_header Referrer-Policy "strict-origin-when-cross-origin" always;
      ```
    - 在 8080 server 块同样添加
  - 验证：`docker exec card-auth-nginx nginx -t` 通过；`curl -I http://localhost:8000` 响应头含上述字段

## [ ] Task 11: compose v2 资源语法 (FR-11 / I5)
- **Priority**: important
- **Depends On**: Task 4
- **Description**:
  - 修改 [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml)：
    - `mem_limit: 2g` → `deploy.resources.limits.memory: 2g`
    - `cpus: 2` → `deploy.resources.limits.cpus: '2'`
    - `pids_limit: 64` → `deploy.resources.limits.pids: 64`
  - 注意：非 swarm 模式下 `deploy.resources.limits` 在 compose v2 中受支持，但需测试
  - 验证：`docker compose -f docker-compose.yml -f docker-compose.prod.yml config` 无 warning

## [ ] Task 12: Alpine mirror 可配 (FR-12 / I6)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [docker/php/Dockerfile](file:///workspace/docker/php/Dockerfile)：
    - 在 FROM 之后添加 `ARG ALPINE_MIRROR=dl-cdn.alpinelinux.org`
    - `sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g'` 改为条件式：
      ```dockerfile
      ARG ALPINE_MIRROR=dl-cdn.alpinelinux.org
      RUN if [ "$ALPINE_MIRROR" != "dl-cdn.alpinelinux.org" ]; then \
              sed -i "s|dl-cdn.alpinelinux.org|${ALPINE_MIRROR}|g" /etc/apk/repositories; \
          fi
      ```
  - 验证：`docker build -t test-php .` 默认走官方源；`docker build --build-arg ALPINE_MIRROR=mirrors.aliyun.com -t test-php .` 走阿里云

## [ ] Task 13: PHP 容器资源限制 (FR-13 / I7)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [docker-compose.yml](file:///workspace/docker-compose.yml)：
    - `php-fpm` 加 `mem_limit: 512m`、`pids_limit: 128`
    - `apk-queue-worker` 加 `mem_limit: 512m`、`pids_limit: 128`
    - `apk-scheduler` 加 `mem_limit: 256m`、`pids_limit: 64`
  - 验证：`docker inspect card-auth-php --format '{{.HostConfig.Memory}}'` 显示 536870912

## [ ] Task 14: 备份目录默认值 (FR-14 / I8)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh](file:///workspace/deploy.sh) `cmd_backup`：
    - `local backup_dir="${BACKUP_DIR:-/data/backups/mysql}"` 改为 `local backup_dir="${BACKUP_DIR:-${SCRIPT_DIR}/backups/mysql}"`
  - 验证：非 root 用户 `./deploy.sh backup` 成功执行

## [ ] Task 15: logs 命令增强 (FR-15 / I9)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh](file:///workspace/deploy.sh) `cmd_logs`：
    - 支持 `--tail N`（默认 100）
    - 支持 `--since TIME`（如 `10m`、`1h`、`2026-07-27T10:00:00`）
    - 支持 `--no-follow` / `-n` 仅打印不跟随
    - 用法示例：`./deploy.sh logs --tail 1000 nginx` / `./deploy.sh logs --since 10m nginx` / `./deploy.sh logs -n nginx`
  - 实现：解析 `$@`，构造 `docker compose logs` 的 `--tail` / `--since` / `-f` 参数
  - 验证：`./deploy.sh logs --tail 1000 nginx` 输出 1000 行后退出

## [ ] Task 16: seccomp syscall 补全 (FR-16 / I10)
- **Priority**: important
- **Depends On**: None
- **Description**:
  - 修改 [deploy/seccomp-apk-inject.json](file:///workspace/deploy/seccomp-apk-inject.json)：
    - 在 `names` 数组中添加 `"clone3"`、`"openat2"`、`"rseq"`（若已存在则跳过）
    - 这些 syscall 是 Java 17 + glibc 2.34+ 创建线程/打开文件所需
  - 验证：`jq '.syscalls[].names | map(select(. == "clone3" or . == "openat2" or . == "rseq"))' deploy/seccomp-apk-inject.json` 输出三个名称

## [ ] Task 17: Minor 批量修复 (FR-17 / M1-M13)
- **Priority**: minor
- **Depends On**: None
- **Description**:
  - **M1** [deploy.sh](file:///workspace/deploy.sh) `cmd_init` `print_access_info`：不打印明文密码，改为 `echo "  默认账号: admin / (见 UserSeeder 或 server/.env)"`
  - **M2** [deploy.sh](file:///workspace/deploy.sh) `env_get`：改用 `awk -F= -v k="$key" '$1==k{sub(/^[^=]*=/,"");print}' "$file"` 精确匹配
  - **M3** [deploy.sh](file:///workspace/deploy.sh) `cmd_install_gvisor`：warning 文案改为"将重启 Docker daemon，影响宿主机上所有容器"
  - **M4** [deploy.sh](file:///workspace/deploy.sh) `health_check`：在 HTTP 200 基础上 `grep -q '"code":0\|ok'` 校验响应体
  - **M5** [docker/php/Dockerfile](file:///workspace/docker/php/Dockerfile)：移除 `mysqli` 扩展安装（项目用 PDO）
  - **M6** [docker/nginx/nginx.prod.conf](file:///workspace/docker/nginx/nginx.prod.conf)：移除 `X-XSS-Protection` header（L26、L109）
  - **M7** [apk-inject-service/Dockerfile](file:///workspace/apk-inject-service/Dockerfile)：移除 `apk add --no-cache bash` 或将 entrypoint 改为 `bash -c`（择一）
  - **M8** [deploy.sh](file:///workspace/deploy.sh) `cmd_reset`：增加 `rm -rf server/public/uploads/`
  - **M9** 抽取 minio-init 到独立 compose 文件或用 `x-minio-init` anchor（可选，仅当不破坏现有结构时）
  - **M10** 保留 `deploy/keystore/.gitkeep`，并在 `.gitignore` 中 `!.gitkeep`
  - **M11** [deploy.sh](file:///workspace/deploy.sh) `cmd_enable_apk_inject`：从环境变量或 compose 配置读取端口，不硬编码 `8081`
  - **M12** [apk-inject-service/src/main/resources/application.yml](file:///workspace/apk-inject-service/src/main/resources/application.yml)：添加 `APK_WORK_DIR` 配置项指向 `/tmp`（与 tmpfs 一致），并在 Java 代码中使用（可选）
  - **M13** [docker-compose.prod.yml](file:///workspace/docker-compose.prod.yml) nginx ulimits：补 `nproc: { soft: 4096, hard: 8192 }`

## [ ] Task 18: 语法验证 + 部署测试
- **Priority**: critical
- **Depends On**: All above
- **Description**:
  - `docker compose -f docker-compose.yml config` 无错误无 warning
  - `docker compose -f docker-compose.yml -f docker-compose.prod.yml config` 无错误无 warning
  - `bash -n deploy.sh` 通过
  - `bash -n quick-start.sh` 通过
  - JSON 校验：`jq . deploy/seccomp-apk-inject.json` 通过
  - 提交并推送到 GitHub main 分支
