# APK 注入修复 - Implementation Plan (Decomposed and Prioritized Task List)

## [ ] Task 1: 并发计数原子化与生命周期修复 (C1, C7, I2, I8)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - `ApkInjectService::createTask()` 移除末尾的 `$redis->incr()`，createTask 不再增减并发计数
  - 新增 `acquireConcurrentSlot(int $merchantId): bool` 私有方法，用 Lua 脚本原子「INCR→若 >MAX 则 DECR 并返回 -1」：
    ```php
    $lua = <<<'LUA'
    local c = redis.call('INCR', KEYS[1])
    if c > tonumber(ARGV[1]) then
        redis.call('DECR', KEYS[1])
        return -1
    end
    redis.call('EXPIRE', KEYS[1], ARGV[2])
    return c
    LUA;
    ```
  - `dispatchTask()` 在 `Queue::push` 前调用 `acquireConcurrentSlot`，失败抛"并发超限"
  - `decrementConcurrent()` 改用 Lua 原子 DECR，若 <0 则 INCR 回补：
    ```php
    $lua = <<<'LUA'
    local c = redis.call('DECR', KEYS[1])
    if c < 0 then
        redis.call('INCR', KEYS[1])
        return 0
    end
    return c
    LUA;
    ```
  - `ApkInjectJob::fire()` 的 `finally` 块保留 decrement 调用（终态确认点）
  - `ApkInjectJob::failed()` 移除 decrement 调用（避免与 finally 双重扣减），仅记录日志
  - `docker-compose.yml` 与 `docker-compose.prod.yml` 的 `apk-queue-worker` command 移除 `--tries=3`（明确失败即终态）
- **Acceptance Criteria Addressed**: AC-F1, AC-F7
- **Test Requirements**:
  - `programmatic` TR-1.1: 并发 10 请求提交第 3 个任务，仅 1 个成功
  - `programmatic` TR-1.2: createTask 后并发计数为 0（未 INCR）
  - `programmatic` TR-1.3: dispatchTask 后并发计数为 1
  - `programmatic` TR-1.4: 任务完成/失败后并发计数回到 0
  - `programmatic` TR-1.5: decrement 不会产生负数
- **Notes**: 此任务为所有后续 PHP 修复的基础

## [ ] Task 2: appSecret 安全流转 + task_token 机制 (C2, C3, I5)
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - **数据库迁移**: `apk_inject_tasks` 表新增 `task_token` 字段（varchar 64），`sdk_config` 列保留但不再写明文 secret
  - **PHP 后端**:
    - `ApkInjectService::createTask()` 生成 `task_token`（`bin2hex(random_bytes(32))`），写入任务表；不再解密 app_secret 到 sdk_config
    - `sdk_config` 只存 `{"app_key": "...", "base_url": "..."}`（不含 app_secret）
    - `ApkInjectTask` 模型新增 `protected $hidden = ['sdk_config']`，detail 接口不返回
    - `ApkInjectJob::callInjectService()` payload 改为传 `task_token` 而非 `app_secret`；Job 执行时从 App 模型解密取 app_secret 直接传给 Java 服务（不落库）
  - **Java 微服务**:
    - `ManifestModifier.java` 删除 `kami_app_secret` meta-data 写入；改为写 `kami_task_token`
    - 保留 `kami_app_key`、`kami_base_url`（非敏感）
  - **SDK 端**:
    - `KamiProxyApplication.readMetaData()` 读 `kami_task_token` 而非 `kami_app_secret`
    - 新增 `SdkAuthClient` 类：用 task_token 调用 `POST /api/v1/sdk/auth` 换取 JWT
    - `CardAuthClient` 新增 JWT 模式构造函数，支持 Bearer token 鉴权
  - **修复 I5**: `KamiProxyApplication.replaceWithOriginalApplication()` 把 `if (appKey == null || appKey.isEmpty())` 改为先读 `originalAppClass`，再判断 `originalAppClass` 是否为空或 `"android.app.Application"`
- **Acceptance Criteria Addressed**: AC-F2, AC-F3
- **Test Requirements**:
  - `programmatic` TR-2.1: DB `sdk_config` 列不含 `app_secret` 字段
  - `programmatic` TR-2.2: detail 接口响应不含 `sdk_config`
  - `programmatic` TR-2.3: APK manifest 不含 `kami_app_secret`
  - `programmatic` TR-2.4: `POST /api/v1/sdk/auth` 用 task_token 换取 JWT 成功
  - `programmatic` TR-2.5: JWT 中不含 app_secret
  - `programmatic` TR-2.6: replaceWithOriginalApplication 在 originalAppClass 为空时正确跳过
- **Notes**: 此任务涉及全栈改动，是最复杂的修复

## [ ] Task 3: 新增 SDK 鉴权 API (JWT 签发与校验) (支撑 C2)
- **Priority**: high
- **Depends On**: Task 2
- **Description**:
  - 新增路由 `POST /api/v1/sdk/auth`（公开接口，无需 HMAC）
  - 新增 `SdkAuthController`：
    - 接收 `{ task_token, device_fingerprint, device_name }`
    - 校验 task_token 对应的任务存在且状态为 COMPLETED
    - 校验 device_fingerprint 格式
    - 签发 JWT（payload 含 `task_id, app_id, merchant_id, app_key`，不含 app_secret，有效期 1 小时）
    - 返回 `{ jwt_token, expires_in, app_key, base_url }`
  - `ApiAuthMiddleware` 新增 JWT Bearer token 鉴权支持（与现有 HMAC 并行）：
    - 检查 `Authorization: Bearer <token>` 头
    - 校验 JWT 签名与有效期
    - 将 payload 注入 request 上下文
  - `JwtService` 新增 `signSdkSession(array $claims): string` 和 `verifySdkSession(string $token): ?array`
  - 卡密验证接口（verify/activate/heartbeat）支持两种鉴权：HMAC（开发者集成）或 JWT（注入 SDK）
- **Acceptance Criteria Addressed**: AC-F3
- **Test Requirements**:
  - `programmatic` TR-3.1: 有效 task_token 换取 JWT 成功
  - `programmatic` TR-3.2: 无效 task_token 返回 401
  - `programmatic` TR-3.3: 已吊销 task_token 返回 401
  - `programmatic` TR-3.4: JWT 过期后调用 verify 返回 401
  - `programmatic` TR-3.5: JWT 鉴权的 verify 请求成功
- **Notes**: task_token 吊销机制暂不实现（Open Question），MVP 阶段 task_token 长期有效

## [ ] Task 4: 任务卡死恢复定时任务 (C6, C7)
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - 新增 `app/command/ApkInjectRecover.php` 命令（继承 `think\console\Command`）
  - 逻辑：
    1. 扫描 `status=PROCESSING AND updated_at < NOW() - 15分钟` 的任务（限 100 条）
    2. 对每条任务：更新为 FAILED（error_log="任务处理超时"）、调用 `decrementConcurrent`
    3. 扫描 `status=PENDING AND created_at < NOW() - 10分钟` 的任务（限 100 条）
    4. 对每条任务：更新为 FAILED（error_log="上传超时未提交"）
  - 注册命令到 `config/console.php`
  - 新增 crontab 或 `php think schedule:run` 调度，每 5 分钟执行一次
  - `docker-compose.yml`/`docker-compose.prod.yml` 新增 `apk-scheduler` 服务或 cron 容器
- **Acceptance Criteria Addressed**: AC-F6, AC-F7
- **Test Requirements**:
  - `programmatic` TR-4.1: 手动构造 PROCESSING 超 15 分钟任务，执行命令后变为 FAILED
  - `programmatic` TR-4.2: 手动构造 PENDING 超 10 分钟任务，执行命令后变为 FAILED
  - `programmatic` TR-4.3: 命令执行后并发计数被正确回收
  - `programmatic` TR-4.4: 命令单次扫描不超过 100 条
- **Notes**: 可复用现有 think-queue 的 cron 调度机制

## [ ] Task 5: 容器沙箱与签名密钥安全 (C4, C5)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - **C4 keystore 修复**:
    - `git rm --cached apk-inject-service/src/main/resources/keystore/platform.keystore`
    - 将 keystore 文件移到 `/workspace/deploy/keystore/platform.keystore`（不入库，加入 .gitignore）
    - `apk-inject-service/Dockerfile` 删除 COPY keystore 行
    - `docker-compose.yml`/`docker-compose.prod.yml` 的 `apk-inject-service` 新增 volume 挂载 `./deploy/keystore:/opt/keystore:ro`
    - `application.yml` 的 `keystore.path` 默认值改为 `/opt/keystore/platform.keystore`
    - 移除 `APK_KEYSTORE_PASSWORD:-changeit` 默认值，改为 `${APK_KEYSTORE_PASSWORD:?APK_KEYSTORE_PASSWORD is required}`
    - `.example.env` 新增 `APK_KEYSTORE_PASSWORD=` 说明
    - `.gitignore` 新增 `deploy/keystore/`
    - 生成新的 keystore 文件并放置到 deploy 目录（不提交）
    - **注意**: 使用 `git filter-repo` 从历史移除 keystore（需用户确认后执行）
  - **C5 gVisor 修复**:
    - `docker-compose.prod.yml` 取消注释 `runtime: runsc`
    - 新增 `deploy/seccomp-apk-inject.json` seccomp profile 作为 gVisor 不可用时的兜底
    - `docker-compose.prod.yml` 添加 `security_opt: ["no-new-privileges:true", "seccomp:./deploy/seccomp-apk-inject.json"]`
    - 新增 `docs/gvisor-setup.md` 说明宿主机安装 runsc 步骤
- **Acceptance Criteria Addressed**: AC-F4, AC-F5
- **Test Requirements**:
  - `programmatic` TR-5.1: `git log --all -- '**/platform.keystore'` 无记录
  - `programmatic` TR-5.2: `git status` 显示 keystore 已从索引移除
  - `programmatic` TR-5.3: `.gitignore` 包含 `deploy/keystore/`
  - `programmatic` TR-5.4: `docker-compose.prod.yml` 中 `runtime: runsc` 未被注释
  - `programmatic` TR-5.5: `APK_KEYSTORE_PASSWORD` 无默认值
- **Notes**: git filter-repo 重写历史需协调（破坏性操作），MVP 阶段可先 `git rm --cached` + 轮换证书

## [ ] Task 6: APK 安全校验补全 (I1, I3)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - **I1 ZIP 炸弹防护** (Java 端):
    - `ApkInjectService.java` 下载 APK 后校验魔数 `PK\x03\x04`（前 4 字节）
    - 新增 `ZipBombChecker.java`：遍历 zip entry，累计未压缩大小，超 500MB 或压缩比 >100:1 抛异常
    - `DexMerger.java` 把 `readAllBytes` 改为流式复制（`transferTo`）
  - **I3 dispatch 校验** (PHP 端):
    - `ApkInjectService::dispatchTask()` 新增 `headObject` 调用，校验 MinIO 文件存在
    - 校验 `Content-Length` 与任务记录的 `file_size` 一致
    - 不一致或不存在抛"文件未上传或大小不匹配"
- **Acceptance Criteria Addressed**: AC-F8
- **Test Requirements**:
  - `programmatic` TR-6.1: 上传非 APK 文件（魔数错误）任务失败
  - `programmatic` TR-6.2: 上传压缩比 200:1 的 ZIP 任务失败
  - `programmatic` TR-6.3: 未上传文件就 dispatch 返回错误
  - `programmatic` TR-6.4: 上传文件大小与声明不符 dispatch 返回错误
- **Notes**: 无依赖，可与 Task 1-4 并行

## [ ] Task 7: MinIO lifecycle 规则修复 (I4)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 `docker-compose.yml` 和 `docker-compose.prod.yml` 的 `minio-init` entrypoint：
    - 删除对独立 bucket `apk-source`/`apk-output`/`apk-temp` 的创建与 lifecycle 配置
    - 改为对 `card-auth` bucket 按前缀配置：
      ```
      mc ilm rule add local/card-auth --prefix "apk-source/" --expire-days 7
      mc ilm rule add local/card-auth --prefix "apk-output/" --expire-days 7
      mc ilm rule add local/card-auth --prefix "apk-temp/" --expire-hours 1
      ```
  - 验证 `mc ilm rule ls local/card-auth` 确认规则命中实际 key
- **Acceptance Criteria Addressed**: FR-F10
- **Test Requirements**:
  - `programmatic` TR-7.1: `mc ilm rule ls local/card-auth` 显示 3 条前缀规则
  - `programmatic` TR-7.2: 上传到 `apk-source/` 前缀的文件 7 天后被清理
  - `programmatic` TR-7.3: 上传到 `apk-temp/` 前缀的文件 1 小时后被清理
- **Notes**: 无依赖，可与其它任务并行

## [ ] Task 8: 卡密校验 UI 与 SDK dex 修复 (I6, I7)
- **Priority**: medium
- **Depends On**: Task 2, Task 3
- **Description**:
  - **I6 卡密校验 UI**:
    - `sdk-module` 新增 `CardVerifyActivity`（已存在则完善）：
      - 布局：卡密输入框 + 提交按钮 + 错误提示
      - 逻辑：调用 `CardAuthClient.verify(cardNo, fingerprint, deviceName)`，成功 finish，失败显示错误
    - `KamiProxyApplication.startCardVerification()` 改为拉起 `CardVerifyActivity`：
      - 注册 `ActivityLifecycleCallbacks`，在第一个 Activity 启动时检查校验状态
      - 未校验通过则 `startActivity` 拉起 `CardVerifyActivity` 并阻塞主 Activity
    - 删除 `client.verify("", ...)` 空卡号调用
  - **I7 主 dex 修复**:
    - `sdk-module/build.gradle` 配置 `multiDexEnabled true` 与 `multiDexKeepFile`
    - `multidex-keep.txt` 列出 `com.cardauth.sdk.KamiProxyApplication`（强制主 dex）
    - 或注入前 `ApkParser` 检查 `minSdk >= 21`，否则拒绝注入
- **Acceptance Criteria Addressed**: AC-F9, FR-F12
- **Test Requirements**:
  - `programmatic` TR-8.1: `KamiProxyApplication` 位于 classes.dex（非 classes2.dex）
  - `programmatic` TR-8.2: minSdk<21 的 APK 注入被拒绝
  - `human-judgement` TR-8.3: 注入后 APK 启动弹出卡密输入界面
  - `human-judgement` TR-8.4: 输入有效卡密后进入主应用
- **Notes**: 需要重新编译 kami-sdk.dex

## [ ] Task 9: checklist 修正与集成验证 (M1)
- **Priority**: medium
- **Depends On**: Task 1, 2, 3, 4, 5, 6, 7, 8
- **Description**:
  - 修正 `/workspace/.trae/specs/apk-cloud-injection/checklist.md` 中与代码事实不符的陈述：
    - Checkpoint 15: `uk_file_sha256` → `idx_file_sha256`（非唯一索引，符合设计）
    - Checkpoint 26: "并发限制使用 Redis 原子计数" → 改为描述 Lua 原子操作
    - Checkpoint 59: "生产环境配置 gVisor" → 确认 `runtime: runsc` 未注释
  - 全量 PHP 语法检查：`find server/app -name '*.php' -exec php -l {} \;`
  - 前端 build 验证：`cd admin && npm run build`
  - Java 微服务编译验证：`cd apk-inject-service && mvn compile`
  - 新增 checklist 文件 `/workspace/.trae/specs/apk-injection-fixes/checklist.md`（见下方）
  - 提交代码并推送到 GitHub main
- **Acceptance Criteria Addressed**: AC-F10
- **Test Requirements**:
  - `programmatic` TR-9.1: 所有 PHP 文件语法检查通过
  - `programmatic` TR-9.2: 前端 npm run build 成功
  - `programmatic` TR-9.3: Java mvn compile 成功
  - `programmatic` TR-9.4: 原 checklist 与代码事实一致
  - `programmatic` TR-9.5: 新 checklist 所有检查点通过
- **Notes**: 最终验证任务，依赖所有前置任务完成
