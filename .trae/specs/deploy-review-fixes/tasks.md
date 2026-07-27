# 一键部署脚本审查修复 - 任务分解

## 任务依赖关系
```
Task 1 (env_get) → Task 4 (run_seeds 检测)
Task 2 (compose_cmd) → Task 8 (force-recreate)
Task 3 (keystore) ← 独立
Task 5 (UserSeeder) ← 独立
Task 6 (vendor) ← 独立
Task 7 (random_hex) ← 独立
Task 9 (chown) ← 独立
Task 10 (mode 切换) ← 依赖 Task 1
Task 11 (macOS) ← 独立
Task 12 (backup.sh) ← 独立
Task 13 (quick-start) ← 独立
Task 14 (install-gvisor) ← 独立
Task 15 (Minor: backup chmod/status/--volumes/.gitignore/JWT) ← 独立
Task 16 (README 更新) ← 依赖所有前置任务
Task 17 (语法验证 + 提交) ← 依赖所有任务
```

---

## [ ] Task 1: 修复 env_get 空值静默返回 (FR-1.2 / C1)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh:120-125](file:///workspace/deploy.sh) 的 `env_get()` 函数
  - 键不存在或值为空时返回非 0
  - 实现要点：
    ```bash
    env_get() {
        local key="$1"
        local file="${2:-.env}"
        [[ -f "$file" ]] || return 1
        local val
        val=$(grep -E "^${key}=" "$file" 2>/dev/null | tail -n1 | cut -d'=' -f2- | tr -d '"') || return 1
        [[ -n "$val" ]] || return 1
        printf '%s' "$val"
    }
    ```
  - 保持对 `key="value"` 与 `key=value` 两种格式兼容
- **Verification**: 
  - 创建测试 .env 含 `MYSQL_ROOT_PASSWORD=abc123`
  - `env_get MYSQL_ROOT_PASSWORD test.env` 返回 0 且输出 `abc123`
  - 删除该行后 `env_get MYSQL_ROOT_PASSWORD test.env` 返回 1

---

## [ ] Task 2: get_compose_cmd 显式加载 override (FR-1.1 / C4)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh:71-79](file:///workspace/deploy.sh) 的 `get_compose_cmd()` 函数
  - 检测 `docker-compose.override.yml` 存在时追加 `-f docker-compose.override.yml`
  - 适用于 dev 与 prod 模式（prod 下 compose v2 不会自动加载）
  - 实现要点：
    ```bash
    get_compose_cmd() {
        local base
        base="$(detect_compose)" || die "未检测到 Docker Compose, 请先安装 Docker"
        local files="-f docker-compose.yml"
        if $PROD_MODE; then
            files="$files -f docker-compose.prod.yml"
        fi
        if [[ -f docker-compose.override.yml ]]; then
            files="$files -f docker-compose.override.yml"
            log_info "已加载 docker-compose.override.yml"
        fi
        echo "$base $files"
    }
    ```
- **Verification**: 
  - prod 模式下创建空 override 文件，调用 `get_compose_cmd` 输出含 `-f docker-compose.override.yml`
  - 删除 override 文件后输出不含该参数

---

## [ ] Task 3: 移除 keystore 密码明文文件 (FR-1.3 / B3)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh:443-446](file:///workspace/deploy.sh) 的 `generate_keystore()` 函数
  - 移除将密码写入 `.keystore-password.txt` 的逻辑
  - 改为 `log_info` 提示"密码请查阅 .env 的 APK_KEYSTORE_PASSWORD"
  - 删除工作树中已存在的 `deploy/keystore/.keystore-password.txt`
  - 修改 [.gitignore](file:///workspace/.gitignore) 增加 `*.keystore-password.txt` 防御性规则
- **Verification**: 
  - 执行 `./deploy.sh init --force` 后 `test ! -f deploy/keystore/.keystore-password.txt`
  - .gitignore 含 `*.keystore-password.txt`

---

## [ ] Task 4: 改进 run_seeds 检测逻辑 (FR-2.4 / C6 - 部分)
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - 修改 [deploy.sh:586-609](file:///workspace/deploy.sh) 的 `run_seeds()` 函数
  - 仅检测 admin 用户存在则跳过 UserSeeder（而非跳过整个 seed:run）
  - 若 admin 不存在，执行 `php think seed:run -s UserSeeder`
  - 其他 seeder (PackageSeeder/SystemConfigSeeder/AdminMenuSeeder) 各自检测目标表非空
  - 实现要点：
    ```bash
    run_seeds() {
        log_step "执行数据填充 (首次)"
        local compose_cmd
        compose_cmd="$(get_compose_cmd)"
        local mysql_pwd
        mysql_pwd="$(env_get MYSQL_ROOT_PASSWORD .env)" || die "无法读取 .env 的 MYSQL_ROOT_PASSWORD"

        # UserSeeder: 检测 admin 用户
        local admin_count
        admin_count=$($compose_cmd exec -T mysql mysql -uroot -p"${mysql_pwd}" card_auth \
            -N -B -e "SELECT COUNT(*) FROM ca_users WHERE username='admin'" 2>/dev/null) || {
            log_error "查询 ca_users 失败, 表可能不存在 (请检查迁移是否成功)"
            return 1
        }
        if [[ "$admin_count" -eq 0 ]]; then
            $compose_cmd exec -T --user root php-fpm sh -c "cd /var/www/server && php think seed:run -s UserSeeder" || {
                log_warn "UserSeeder 执行失败"
                return 1
            }
            log_info "UserSeeder 完成 ✓"
        else
            log_info "admin 用户已存在, 跳过 UserSeeder"
        fi

        # 其他 seeder: 检测目标表非空 (, 这里简化为只在首次执行)
        local pkg_count
        pkg_count=$($compose_cmd exec -T mysql mysql -uroot -p"${mysql_pwd}" card_auth \
            -N -B -e "SELECT COUNT(*) FROM ca_packages" 2>/dev/null) || pkg_count=0
        if [[ "$pkg_count" -eq 0 ]]; then
            $compose_cmd exec -T --user root php-fpm sh -c "cd /var/www/server && php think seed:run -s PackageSeeder" || log_warn "PackageSeeder 失败"
        fi
        # 同理处理 SystemConfigSeeder / AdminMenuSeeder (检测 ca_system_configs / ca_admin_menus)
    }
    ```
- **Verification**: 
  - admin 已存在但 ca_packages 为空时，仍执行 PackageSeeder

---

## [ ] Task 5: UserSeeder 幂等性修复 (FR-2.4 / C6 - 部分)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 修改 [server/database/seeds/UserSeeder.php](file:///workspace/server/database/seeds/UserSeeder.php)
  - 改用 Phinx 的 `insert` + `onConflict` 语法实现 upsert
  - 实现要点：
    ```php
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $passwordHash = password_hash('admin123456', PASSWORD_BCRYPT);

        $users = [
            [
                'id'              => 1,
                'username'        => 'admin',
                'password_hash'   => $passwordHash,
                'email'           => 'admin@example.com',
                'phone'           => '13800138000',
                'role_type'       => 1,
                'parent_id'       => 0,
                'avatar'          => '',
                'status'          => 1,
                'login_fail_count' => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];

        // 幂等: 已存在则更新 password_hash/updated_at, 否则插入
        $this->table('ca_users')
            ->insert($users)
            ->onConflict(['id'])
            ->replace()
            ->save();
    }
    ```
- **Verification**: 
  - 重复执行 `php think seed:run -s UserSeeder` 不报主键冲突
  - admin 用户的 password_hash 会被更新为最新值

---

## [ ] Task 6: install_php_deps 检测 vendor 完整性 (FR-2.3 / C5)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh:541-566](file:///workspace/deploy.sh) 的 `install_php_deps()` 函数
  - 检测 `vendor/autoload.php` 与 `vendor/composer/installed.json` 同时存在
  - 任一缺失则清理残缺 vendor 并重新安装
  - 实现要点：
    ```bash
    install_php_deps() {
        log_step "安装 PHP 依赖 (composer)"
        local compose_cmd
        compose_cmd="$(get_compose_cmd)"

        # 检测 vendor 完整性 (目录存在 + autoload.php + installed.json)
        local vendor_complete
        if $compose_cmd exec -T php-fpm sh -c "test -f /var/www/server/vendor/autoload.php && test -f /var/www/server/vendor/composer/installed.json" 2>/dev/null; then
            vendor_complete=true
        else
            vendor_complete=false
        fi

        if $vendor_complete; then
            log_info "vendor 完整, 跳过"
            return 0
        fi

        # 清理残缺 vendor
        if $compose_cmd exec -T php-fpm sh -c "test -d /var/www/server/vendor" 2>/dev/null; then
            log_warn "vendor 残缺, 清理后重装"
            $compose_cmd exec -T --user root php-fpm sh -c "rm -rf /var/www/server/vendor /var/www/server/composer.lock"
        fi

        # 用 --user root 执行 composer
        local composer_flags=""
        if $PROD_MODE; then
            composer_flags="--no-dev --optimize-autoloader"
        fi
        $compose_cmd exec -T --user root -e COMPOSER_ALLOW_SUPERUSER=1 php-fpm \
            sh -c "cd /var/www/server && composer install $composer_flags"

        # 修复 vendor 权限
        $compose_cmd exec -T --user root php-fpm \
            sh -c "cd /var/www/server && chmod -R a+r vendor && find vendor -type d -exec chmod a+x {} +"
        log_info "PHP 依赖安装完成 ✓"
    }
    ```
- **Verification**: 
  - 删除 vendor/autoload.php 后执行 `up`，应触发清理并重装

---

## [ ] Task 7: random_hex 兜底 + doctor 检测 openssl (FR-2.5 / C7)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh:98-102](file:///workspace/deploy.sh) 的 `random_hex()` 函数
  - 优先 `openssl rand -hex`，失败时用 `/dev/urandom` 兜底
  - `cmd_doctor` 新增 openssl 检测项
  - 实现要点：
    ```bash
    random_hex() {
        local len="${1:-16}"
        if command -v openssl >/dev/null 2>&1; then
            openssl rand -hex "$len"
        elif [[ -r /dev/urandom ]]; then
            head -c "$len" /dev/urandom | od -An -tx1 | tr -d ' \n' | head -c $((len*2))
        else
            die "无法生成随机数: openssl 与 /dev/urandom 均不可用"
        fi
    }
    ```
  - doctor 中增加：
    ```bash
    if command -v openssl >/dev/null 2>&1; then
        log_ok "openssl: $(openssl version)"
    else
        log_warn "openssl 未安装 (将回退到 /dev/urandom 生成随机数)"
    fi
    ```
- **Verification**: 
  - 临时 PATH 屏蔽 openssl 后调用 `random_hex 16` 仍能输出 32 字符

---

## [ ] Task 8: override 变化时强制 recreate (FR-2.8 / F1)
- **Priority**: high
- **Depends On**: Task 2
- **Description**:
  - 修改 `handle_port_conflicts()` 函数（[deploy.sh:631-687](file:///workspace/deploy.sh) 附近）
  - 生成/更新 override 时设置全局 `FORCE_RECREATE=true`
  - `start_services()` 检测该标志，追加 `--force-recreate`
  - 实现要点：
    ```bash
    # handle_port_conflicts 末尾 (生成/更新 override 后)
    if $need_override; then
        FORCE_RECREATE=true
    fi

    # start_services 中
    local recreate_flag=""
    if [[ "${FORCE_RECREATE:-false}" == "true" ]]; then
        recreate_flag="--force-recreate"
        log_info "检测到端口映射变化, 强制重建容器"
    fi
    $compose_cmd up -d $recreate_flag $services
    ```
  - 注意：`FORCE_RECREATE` 在 `cmd_up` 开头重置为 false
- **Verification**: 
  - 首次生成 override 后执行 `up`，应包含 `--force-recreate`

---

## [ ] Task 9: docker run 后修复文件属主 (FR-2.9 / F5/F6)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 `build_frontend()` ([deploy.sh:462-468](file:///workspace/deploy.sh) 附近)
  - 修改 `generate_keystore()` ([deploy.sh:427-441](file:///workspace/deploy.sh) 附近)
  - docker run 后用宿主机当前用户 chown 修复属主
  - 实现要点：
    ```bash
    # build_frontend 末尾
    if [[ -d "${SCRIPT_DIR}/admin/dist" ]]; then
        sudo chown -R "$(id -u):$(id -g)" "${SCRIPT_DIR}/admin/dist" "${SCRIPT_DIR}/admin/node_modules" 2>/dev/null || \
            chown -R "$(id -u):$(id -g)" "${SCRIPT_DIR}/admin/dist" "${SCRIPT_DIR}/admin/node_modules" 2>/dev/null || true
    fi

    # generate_keystore 末尾 (在 chmod 600 之前)
    sudo chown "$(id -u):$(id -g)" "$keystore_file" 2>/dev/null || \
        chown "$(id -u):$(id -g)" "$keystore_file" 2>/dev/null || true
    chmod 600 "$keystore_file"
    ```
- **Verification**: 
  - 非 root 用户执行 `init`，生成的 keystore 文件属主为当前用户

---

## [ ] Task 10: dev→prod 模式切换警告 (FR-2.6 / D1)
- **Priority**: medium
- **Depends On**: Task 1
- **Description**:
  - 修改 `cmd_init()` ([deploy.sh:291-294](file:///workspace/deploy.sh) 附近)
  - 检测当前 `server/.env` 的 `APP_DEBUG` 值
  - 若与目标模式冲突，输出警告并要求确认
  - 实现要点：
    ```bash
    cmd_init() {
        log_step "初始化配置"
        # 检测模式切换
        if [[ -f server/.env ]]; then
            local current_debug
            current_debug=$(env_get APP_DEBUG server/.env 2>/dev/null) || current_debug=""
            if [[ -n "$current_debug" ]]; then
                local target_debug="true"
                $PROD_MODE && target_debug="false"
                if [[ "$current_debug" != "$target_debug" ]]; then
                    log_warn "检测到模式切换 (APP_DEBUG: $current_debug → $target_debug)"
                    log_warn "若用 --force 重新生成密钥, MySQL/MinIO 数据卷将无法用新密码访问"
                    log_warn "建议先执行 ./deploy.sh reset --yes 清理数据卷"
                    $ASSUME_YES || {
                        read -r -p "确认继续? [y/N] " ans
                        [[ "$ans" =~ ^[Yy]$ ]] || exit 0
                    }
                fi
            fi
        fi
        # ... 原有逻辑
    }
    ```
- **Verification**: 
  - dev 已部署后执行 `--prod init --force`，应有警告提示

---

## [ ] Task 11: macOS hostname 兼容 (FR-2.7 / E1)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 `print_access_info()` ([deploy.sh:725-752](file:///workspace/deploy.sh) 附近)
  - macOS 下用 `ipconfig getifaddr en0`，失败回退 localhost
  - Linux 下保持 `hostname -I`
  - 实现要点：
    ```bash
    print_access_info() {
        log_step "部署完成 - 访问信息"
        local host="${DOMAIN}"
        if [[ -z "$host" ]]; then
            if [[ "$(uname)" == "Darwin" ]]; then
                host=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "localhost")
            else
                host=$(hostname -I 2>/dev/null | awk '{print $1}')
                host="${host:-localhost}"
            fi
        fi
        # ... 原有逻辑
    }
    ```
  - 同时修复 [deploy.sh:234-238](file:///workspace/deploy.sh) doctor 中 df 分支冗余（E2）
- **Verification**: 
  - macOS 下 `print_access_info` 输出含有效 IP，不含 `http://:`

---

## [ ] Task 12: scripts/backup.sh 移除默认弱密码 (FR-2.10 / G4)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 修改 [scripts/backup.sh:9](file:///workspace/scripts/backup.sh)
  - 移除 `:-root123456` 兜底，改为 `${VAR:?error}` 强制要求
  - 实现要点：
    ```bash
    MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD:?必须设置 MYSQL_ROOT_PASSWORD 环境变量}"
    ```
  - 同时修复 [deploy.sh:846](file:///workspace/deploy.sh) 的 `cmd_backup` 备份目录权限（FR-3.1 / H1）：
    ```bash
    mkdir -p "$backup_dir" && chmod 700 "$backup_dir"
    ```
- **Verification**: 
  - 不导出 MYSQL_ROOT_PASSWORD 执行 `bash scripts/backup.sh`，应退出并提示

---

## [ ] Task 13: quick-start.sh 移除 sudo -E (FR-2.1 / B6)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [quick-start.sh:212,219](file:///workspace/quick-start.sh)
  - 将 `sudo -E ./deploy.sh` 改为 `sudo ./deploy.sh`
- **Verification**: 
  - `grep "sudo -E" quick-start.sh` 无输出

---

## [ ] Task 14: install-gvisor 添加 set -e 与重启警告 (FR-2.2 / B7)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [deploy.sh:1009-1016](file:///workspace/deploy.sh) 的 `cmd_install_gvisor()`
  - sudo bash -c 块内首行加 `set -euo pipefail`
  - `gpg --dearmor` 加 `--yes`
  - 安装前 `log_warn` 提示重启风险并要求确认
  - 实现要点：
    ```bash
    cmd_install_gvisor() {
        log_step "安装 gVisor (runsc)"
        if [[ "$(uname)" != "Linux" ]]; then
            die "gVisor 仅支持 Linux"
        fi
        if command -v runsc >/dev/null 2>&1; then
            log_info "gVisor 已安装: $(runsc --version 2>&1 | head -1)"
            return 0
        fi
        log_warn "安装将重启 Docker daemon, 所有运行中容器会被中断"
        $ASSUME_YES || {
            read -r -p "继续? [y/N] " ans
            [[ "$ans" =~ ^[Yy]$ ]] || exit 0
        }
        sudo bash -c '
            set -euo pipefail
            curl -fsSL https://gvisor.dev/archive.key | gpg --dearmor --yes -o /usr/share/keyrings/gvisor-archive-keyring.gpg
            echo "deb [arch=amd64 signed-by=/usr/share/keyrings/gvisor-archive-keyring.gpg] https://storage.googleapis.com/gvisor/releases release main" > /etc/apt/sources.list.d/gvisor.list
            apt-get update
            apt-get install -y runsc
            runsc install
            systemctl restart docker
        '
        log_info "gVisor 安装完成 ✓"
        log_warn "请在 /etc/docker/daemon.json 中配置 runsc 为默认 runtime (或用 --runtime=runsc)"
    }
    ```
- **Verification**: 
  - 模拟 curl 失败时脚本中断（手动断网测试）

---

## [ ] Task 15: Minor 修复集合 (FR-3.1~3.5)
- **Priority**: low
- **Depends On**: None
- **Description**:
  - **FR-3.2 cmd_status 端口检查语义** ([deploy.sh:811-821](file:///workspace/deploy.sh))：明确标注"宿主机端口连通性（若启用端口冲突 override，mysql/redis 可能不监听宿主机）"
  - **FR-3.3 cmd_down --volumes 解析** ([deploy.sh:792](file:///workspace/deploy.sh))：用循环遍历 EXTRA_ARGS
    ```bash
    local has_volumes=false
    for arg in "${EXTRA_ARGS[@]:-}"; do
        [[ "$arg" == "--volumes" ]] && has_volumes=true
    done
    ```
  - **FR-3.4 JWT secret 长度** ([deploy.sh:337-338](file:///workspace/deploy.sh))：`jwt_secret=$(random_hex 32)`
  - **FR-3.5 .gitignore 补充** ([.gitignore](file:///workspace/.gitignore))：增加 `*.sql` / `*.sql.gz` / `*.pem`
- **Verification**: 
  - `grep "random_hex 32" deploy.sh` 输出 jwt_secret 行
  - `./deploy.sh down --yes --foo --volumes` 能识别 --volumes

---

## [ ] Task 16: README 更新 (G2/G4/G5)
- **Priority**: low
- **Depends On**: Task 12
- **Description**:
  - 修改 [README.md](file:///workspace/README.md)
  - **G2**: 端口说明标注"127.0.0.1:3306（仅本机可访问）"
  - **G4**: 手动备份命令改为 `docker compose exec mysql bash /workspace/scripts/backup.sh`，说明需从 .env 导出 MYSQL_ROOT_PASSWORD
  - **G5**: 数据库表数量更新（追加 ca_apk_inject_tasks）
- **Verification**: 
  - README 备份章节含 docker compose exec 命令

---

## [ ] Task 17: 语法验证 + 提交推送
- **Priority**: high
- **Depends On**: All
- **Description**:
  - `bash -n deploy.sh` 语法检查
  - `bash -n quick-start.sh` 语法检查
  - `php -l server/database/seeds/UserSeeder.php` 语法检查
  - `./deploy.sh doctor` 实测（验证 openssl 检测项）
  - git add 所有改动并提交，commit message 详细列出修复项
  - 推送到 origin main
- **Verification**: 
  - 所有语法检查通过
  - doctor 实测输出正常
  - git push 成功

---

## 执行顺序建议
1. **批次 A (Critical + 高优先级 Important)**: Task 1, 2, 3, 5, 6, 8 (并行)
2. **批次 B (Important)**: Task 4, 7, 9, 10, 11, 12, 13, 14 (并行)
3. **批次 C (Minor + 文档)**: Task 15, 16
4. **批次 D (验证)**: Task 17
