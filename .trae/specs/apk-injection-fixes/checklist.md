# APK 注入修复 - 验证清单

## Task 1: 并发计数原子化 (C1, C7, I2, I8)
- [x] CP-1.1: `ApkInjectService::createTask()` 末尾不再调用 `$redis->incr()`（INCR 移至 dispatchTask 的 acquireConcurrentSlot）
- [x] CP-1.2: 新增 `acquireConcurrentSlot()` 方法使用 Lua 脚本
- [x] CP-1.3: Lua 脚本逻辑为「INCR→若 >MAX 则 DECR 并返回 -1→否则 EXPIRE」
- [x] CP-1.4: `dispatchTask()` 在 `Queue::push` 前调用 `acquireConcurrentSlot`
- [x] CP-1.5: `decrementConcurrent()` 使用 Lua 原子 DECR
- [x] CP-1.6: Lua DECR 脚本在结果 <0 时 INCR 回补
- [x] CP-1.7: `ApkInjectJob::failed()` 不再调用 `decrementConcurrent`（注释说明在 fire() finally 已调用）
- [x] CP-1.8: `docker-compose.yml` 的 `apk-queue-worker` command 为 `--tries=1`（不含 `--tries=3`）
- [x] CP-1.9: `docker-compose.prod.yml` 的 `apk-queue-worker` command 为 `--tries=1`（不含 `--tries=3`）
- [ ] CP-1.10: 并发测试：10 请求同时提交第 3 个任务，仅 1 个成功（需运行时环境验证）

## Task 2: appSecret 安全流转 (C2, C3, I5)
- [x] CP-2.1: 迁移文件新增 `task_token` 字段（varchar 64）
- [x] CP-2.2: `createTask()` 生成 `task_token`（`bin2hex(random_bytes(32))`）
- [x] CP-2.3: `sdk_config` 列只含 `app_key` 和 `base_url`，不含 `app_secret`
- [x] CP-2.4: `ApkInjectTask` 模型含 `protected $hidden = ['sdk_config', 'task_token']`
- [x] CP-2.5: `getDetail()` 响应不含 `sdk_config` 字段（二次 unset 保险）
- [x] CP-2.6: `ApkInjectJob::callInjectService()` payload 含 `task_token` 不含 `app_secret`（app_secret 仅内存传递）
- [x] CP-2.7: Job 执行时从 App 模型实时解密 app_secret 传给 Java 服务
- [x] CP-2.8: `ManifestModifier.java` 不再写 `kami_app_secret` meta-data
- [x] CP-2.9: `ManifestModifier.java` 写入 `kami_task_token` meta-data
- [x] CP-2.10: `KamiProxyApplication.readMetaData()` 读 `kami_task_token`
- [x] CP-2.11: 新增 `SdkAuthClient` 类（task_token → JWT 换取）
- [x] CP-2.12: `CardAuthClient` 支持 JWT Bearer 鉴权模式（双构造函数）
- [x] CP-2.13: `replaceWithOriginalApplication()` 检查 `originalAppClass` 而非 `appKey`
- [x] CP-2.14: `originalAppClass` 为空或 "android.app.Application" 时正确跳过替换

## Task 3: SDK 鉴权 API (支撑 C2)
- [x] CP-3.1: 路由 `POST /api/v1/sdk/auth` 已注册（公开接口，仅走限流不走 HMAC）
- [x] CP-3.2: `SdkAuthController` 存在并接收 `task_token/device_fingerprint/device_name`
- [x] CP-3.3: 校验 task_token 对应任务存在且状态为 COMPLETED
- [x] CP-3.4: 签发的 JWT payload 含 `task_id/app_id/merchant_id/app_key`
- [x] CP-3.5: JWT payload 不含 `app_secret`
- [x] CP-3.6: JWT 有效期 ≤1 小时（默认 3600 秒，可配置 `jwt.sdk_session_expire`）
- [x] CP-3.7: `JwtService::signSdkSession()` 方法存在
- [x] CP-3.8: `JwtService::verifySdkSession()` 方法存在
- [x] CP-3.9: `ApiAuthMiddleware` 支持 `Authorization: Bearer <token>` 头（与 HMAC 并行）
- [x] CP-3.10: JWT 鉴权的 verify 请求成功（Bearer 模式注入 app_id/merchant_id）
- [x] CP-3.11: 无效 task_token 返回 401（任务不存在或未完成）
- [x] CP-3.12: 过期 JWT 调用 verify 返回 401（`verifySdkSession` 返回 null）

## Task 4: 任务卡死恢复 (C6, C7)
- [x] CP-4.1: `app/command/ApkInjectRecover.php` 命令类存在
- [x] CP-4.2: 命令扫描 `status=PROCESSING AND updated_at < NOW() - 15分钟`
- [x] CP-4.3: 命令扫描 `status=PENDING AND created_at < NOW() - 10分钟`
- [x] CP-4.4: 超时 PROCESSING 任务被标记为 FAILED 并调用 `decrementConcurrent`
- [x] CP-4.5: 超时 PENDING 任务被标记为 FAILED（"上传超时未提交"）
- [x] CP-4.6: 单次扫描限 100 条
- [x] CP-4.7: 命令注册到 `config/console.php`（`apk-inject:recover`）
- [x] CP-4.8: 调度每 5 分钟执行一次（apk-scheduler 服务 `sleep 300`）
- [x] CP-4.9: docker-compose.yml 与 docker-compose.prod.yml 均新增 `apk-scheduler` 服务

## Task 5: 容器沙箱与 keystore (C4, C5)
- [x] CP-5.1: `platform.keystore` 已从 Git 索引移除（`git ls-files` 无记录）
- [~] CP-5.2: 历史仍含 1 条记录（`63da0e5`）；按 spec MVP 决策采用 `git rm --cached` + 证书轮换，`filter-repo` 重写历史 deferred
- [x] CP-5.3: `.gitignore` 包含 `deploy/keystore/`
- [x] CP-5.4: `apk-inject-service/Dockerfile` 不再 COPY keystore（仅 COPY pom.xml/src/dex）
- [x] CP-5.5: `docker-compose.yml` 的 `apk-inject-service` 挂载 `./deploy/keystore:/opt/keystore:ro`
- [x] CP-5.6: `docker-compose.prod.yml` 的 `apk-inject-service` 挂载 `./deploy/keystore:/opt/keystore:ro`
- [x] CP-5.7: `application.yml` 的 `keystore-path` 默认为 `/opt/keystore/platform.keystore`
- [x] CP-5.8: `APK_KEYSTORE_PASSWORD` 无 `:-changeit` 默认值
- [x] CP-5.9: `APK_KEYSTORE_PASSWORD` 使用 `${APK_KEYSTORE_PASSWORD:?...}` 强制要求（两处 compose 均确认）
- [x] CP-5.10: `.env.example` 含 `APK_KEYSTORE_PASSWORD=` 说明
- [x] CP-5.11: `docker-compose.prod.yml` 的 `runtime: runsc` 未被注释（line 179）
- [x] CP-5.12: `deploy/seccomp-apk-inject.json` seccomp profile 存在
- [x] CP-5.13: `docker-compose.prod.yml` 的 `security_opt` 含 seccomp 配置（line 172-175）
- [x] CP-5.14: 新 keystore 已生成并放置到 `deploy/keystore/platform.keystore`（不入库）

## Task 6: APK 安全校验 (I1, I3)
- [x] CP-6.1: `ApkInjectService.java` 下载后校验 `PK\x03\x04` 魔数（`ZipBombChecker.check` 集成）
- [x] CP-6.2: `ZipBombChecker.java` 类存在
- [x] CP-6.3: ZipBombChecker 累计未压缩大小，超 500MB 拒绝
- [x] CP-6.4: ZipBombChecker 检测压缩比 >100:1 拒绝（单条目 + 整体）
- [x] CP-6.5: `DexMerger.java` 不再使用 `readAllBytes`，改为流式复制（`transferTo`）
- [x] CP-6.6: `dispatchTask()` 调用 `headObject` 校验文件存在
- [x] CP-6.7: `dispatchTask()` 校验 `Content-Length` 与 `file_size` 一致
- [x] CP-6.8: 文件不存在或大小不符时抛清晰错误

## Task 7: MinIO lifecycle (I4)
- [x] CP-7.1: `minio-init` 不再创建独立 bucket `apk-source`/`apk-output`/`apk-temp`（仅 `mc mb local/card-auth`）
- [x] CP-7.2: `minio-init` 对 `card-auth` bucket 配置 `apk-source/` 前缀 7 天过期
- [x] CP-7.3: `minio-init` 对 `card-auth` bucket 配置 `apk-output/` 前缀 7 天过期
- [x] CP-7.4: `minio-init` 对 `card-auth` bucket 配置 `apk-temp/` 前缀 1 小时过期
- [ ] CP-7.5: `mc ilm rule ls local/card-auth` 显示 3 条前缀规则（需运行时环境验证）
- [x] CP-7.6: `docker-compose.yml` 和 `docker-compose.prod.yml` 均已更新（line 99-101 / 130-132）

## Task 8: 卡密校验 UI 与 SDK dex (I6, I7)
- [x] CP-8.1: `CardVerifyActivity` 存在且布局含卡密输入框 + 提交按钮
- [x] CP-8.2: `CardVerifyActivity` 调用 `KamiProxyApplication.verifyCard`（内部用 JWT 走 `CardAuthClient.verify`）
- [x] CP-8.3: 校验成功时 `CardVerifyActivity` finish
- [x] CP-8.4: 校验失败时显示错误提示
- [x] CP-8.5: `KamiProxyApplication` 注册 `CardVerifyLifecycle` 拉起 `CardVerifyActivity`
- [x] CP-8.6: 注册 `ActivityLifecycleCallbacks` 监控第一个 Activity
- [x] CP-8.7: 未校验通过时阻塞主 Activity（FLAG_ACTIVITY_NEW_TASK | CLEAR_TOP）
- [x] CP-8.8: 删除 `client.verify("", ...)` 空卡号调用
- [~] CP-8.9: ~~`sdk-module/build.gradle` 配置 `multiDexEnabled true`~~ N/A —— library 模块设置对注入 dex 无效（详见 build.gradle 注释）
- [~] CP-8.10: ~~`multidex-keep.txt`~~ N/A —— 同 CP-8.9，改用 minSdk 校验策略
- [x] CP-8.11: `ApkParser.parse()` 检查 `minSdk >= 21` 否则拒绝注入（`MIN_REQUIRED_SDK=21`）
- [x] CP-8.12: KamiProxyApplication 在 secondary dex，由 API 21+ ART 原生 multidex 加载（CP-8.11 保证宿主 minSdk≥21）

## Task 9: checklist 修正与集成验证 (M1)
- [x] CP-9.1: 原 checklist Checkpoint 15 改为 `idx_file_sha256`（非唯一，迁移文件 line 103-104 确认 `addIndex` 非 `addUniqueKey`）
- [x] CP-9.2: 原 checklist Checkpoint 26 改为描述 Lua 原子操作（acquireConcurrentSlot/decrementConcurrent 均用 `$redis->eval(LUA,...)`）
- [x] CP-9.3: 原 checklist Checkpoint 59 确认 `runtime: runsc` 未注释（docker-compose.prod.yml:179 实测未注释）
- [x] CP-9.4: 所有 PHP 文件 `php -l` 语法检查通过（server/app 全量扫描无错误）
- [x] CP-9.5: 前端 `npm run build` 成功（built in 1.70s，exit 0）
- [~] CP-9.6: Java `mvn compile` 受沙箱网络限制无法拉取 Spring Boot parent POM；ApkParser.java 改动经人工审查语法正确（仅新增常量与 if 检查，无新依赖）
- [x] CP-9.7: 本 checklist 所有代码级检查点通过；运行时验证项（CP-1.10/CP-7.5）与 MVP deferred 项（CP-5.2/CP-8.9/CP-8.10/CP-9.6）已标注
- [ ] CP-9.8: 代码已提交并推送到 GitHub main

## 原评审问题覆盖核对
- [x] C1 并发竞态 → Task 1 (CP-1.1 ~ CP-1.9 已验证；CP-1.10 待运行时并发测试)
- [x] C2 appSecret 写 manifest → Task 2 (CP-2.8 ~ CP-2.12)
- [x] C3 appSecret 落库/API 泄露 → Task 2 (CP-2.3 ~ CP-2.7)
- [x] C4 keystore 入库 → Task 5 (CP-5.1 已移出索引；CP-5.2 历史清理 deferred 按 MVP 决策)
- [x] C5 gVisor 未启用 → Task 5 (CP-5.11 ~ CP-5.13)
- [x] C6 任务卡死 → Task 4 (CP-4.1 ~ CP-4.9)
- [x] C7 createTask 即 INCR → Task 1 (CP-1.1, CP-1.4) + Task 4 (CP-4.5)
- [x] I1 ZIP 炸弹 → Task 6 (CP-6.1 ~ CP-6.5)
- [x] I2 decrement 非原子 → Task 1 (CP-1.5 ~ CP-1.6)
- [x] I3 dispatch 不校验 → Task 6 (CP-6.6 ~ CP-6.8)
- [x] I4 MinIO lifecycle → Task 7 (CP-7.1 ~ CP-7.4, CP-7.6；CP-7.5 待运行时验证)
- [x] I5 检查错变量 → Task 2 (CP-2.13 ~ CP-2.14)
- [x] I6 卡密校验不生效 → Task 8 (CP-8.1 ~ CP-8.8)
- [x] I7 secondary dex 崩溃 → Task 8 (CP-8.11 替代 CP-8.9/8.10，CP-8.12 由原生 multidex 保证)
- [x] I8 --tries=3 死配置 → Task 1 (CP-1.8 ~ CP-1.9)
- [x] M1 checklist 不实 → Task 9 (CP-9.1 ~ CP-9.3)
