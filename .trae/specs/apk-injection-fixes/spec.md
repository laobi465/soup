# APK 云端注入功能 - 安全与可靠性修复 PRD

## Overview
- **Summary**: 修复 APK 云端注入功能代码评审中发现的 7 个 Critical 和 8 个 Important 问题，使功能达到可上线状态。核心修复包括：并发计数原子化、appSecret 安全流转（不落地 APK/DB/API）、签名密钥移出仓库、gVisor 沙箱启用、任务卡死恢复、APK 安全校验补全、卡密校验 UI 实装。
- **Purpose**: 消除上线阻断风险——商户凭证泄露、并发限制可绕过、任务永久卡死、不可信输入容器无隔离等。
- **Target Users**: 平台运维（部署修复）、商户（使用注入功能）、终端用户（运行注入后 APK）

## Background & Context
- **评审报告**: `/workspace/.trae/reviews/apk-injection-review-20260727.md`
- **原功能 Spec**: `/workspace/.trae/specs/apk-cloud-injection/spec.md`
- **当前状态**: MVP 代码已实现并推送，但代码评审结论为 "Not ready"，存在 7 个 Critical 阻断问题
- **关键事实**: HEAD 提交 `d950116` 实为"安装 GitHub 仓库"，前轮评审指出的并发竞态等核心问题在 diff 中未见实质性修复

## Goals
- 修复全部 7 个 Critical 问题（C1-C7）
- 修复全部 8 个 Important 问题（I1-I8）
- 修正 `checklist.md` 中与代码事实不符的自评陈述（M1）
- 修复后通过代码复审

## Non-Goals (Out of Scope)
- Minor 问题 M2-M8（设备指纹增强、dev 配置对齐、error 脱敏、入参校验加强、下载限流、aapt2 边界、aapt2 提取）——记入后续迭代
- 每商户独立 keystore（仍用平台统一签名，但 keystore 不入库）
- iOS / HarmonyOS 注入
- 加壳 APK 的非侵入式注入

## Critical Issues to Fix

### C1: 并发限制 check-then-act 竞态（未修复）
- **现状**: `ApkInjectService::createTask()` 先 `GET` 读计数判断 ≥3，隔 50 行业务后再 `INCR`，两个并发请求可同时通过校验导致超限
- **修复**: 用 Lua 脚本实现原子「INCR→若 >MAX 则 DECR 并返回失败」

### C2: appSecret 明文写入 APK manifest meta-data
- **现状**: `ManifestModifier.java:107-109` 把 `app_key/app_secret/base_url` 明文写入 AndroidManifest
- **修复**: 改用任务令牌（task_token）机制——manifest 只写 `task_token`，SDK 运行时用 token 调用平台换取短期 JWT，appSecret 永不落地 APK

### C3: appSecret 明文落库 + detail 接口泄露
- **现状**: `ApkInjectService.php:56-69` 解密后明文写入 `sdk_config` JSON 列；`getDetail()` 原样返回
- **修复**: 任务表不存明文 app_secret；Job 执行时从 App 模型实时解密取用；模型 `$hidden` 屏蔽 `sdk_config`

### C4: 平台签名 keystore 入库 + 默认密码
- **现状**: `platform.keystore` 提交进 Git（blob 28ff378），密码硬编码 `changeit`
- **修复**: 从 Git 历史移除、轮换证书、运行时通过 Docker secret/挂载注入、密码强制环境变量

### C5: gVisor 沙箱未启用却在自评中谎称已配置
- **现状**: `docker-compose.prod.yml:172` `# runtime: runsc` 被注释
- **修复**: 取消注释启用 gVisor；若运行时不支持则配置等价 seccomp profile；修正 checklist

### C6: Worker 崩溃后任务永久卡在"处理中"
- **现状**: 无看门狗/超时回收机制
- **修复**: 新增定时任务扫描 `status=PROCESSING AND updated_at < NOW() - 15分钟`，标记失败并回收并发计数

### C7: createTask 即 INCR，放弃上传导致计数泄漏
- **现状**: `createTask` 返回即 +1，但用户可能从未上传或未 dispatch
- **修复**: INCR 移到 `dispatchTask`（真正投队列时）；PENDING 超 10 分钟未 dispatch 的任务回收

## Important Issues to Fix

### I1: 缺少 APK 真实性校验与 ZIP 炸弹防护（FR-12 未实现）
- **修复**: Java 端校验 `PK\x03\x04` 魔数；遍历 entry 累计未压缩大小，超 500MB 或压缩比 >100:1 拒绝；DexMerger 流式复制

### I2: decrementConcurrent 非原子 TOCTOU
- **修复**: 用 Lua 原子 DECR，若 <0 则 INCR 回补

### I3: dispatch 不校验文件是否已上传
- **修复**: `dispatchTask` 中 `headObject` 校验存在性与 Content-Length

### I4: MinIO lifecycle 规则作用对象错误
- **现状**: 规则建在空 bucket `apk-source`/`apk-output` 上，实际写入 `card-auth` bucket 的前缀下
- **修复**: 改为按前缀配置 `mc ilm rule add --expire-hours 1 local/card-auth --prefix "apk-source/"`

### I5: replaceWithOriginalApplication 检查错变量
- **现状**: 检查 `appKey` 而非 `originalAppClass`
- **修复**: 先读 `originalAppClass`，再判断是否需要替换

### I6: 卡密校验实际不生效（verify 传空卡号 + TODO UI）
- **修复**: 实现 `CardVerifyActivity` 弹窗，校验未通过时阻塞主 Activity

### I7: SDK 在 secondary dex 致 minSdk<21 启动崩溃
- **修复**: 把 `KamiProxyApplication` 编进主 `classes.dex`，其余 SDK 类放 secondary dex；或注入前检查 minSdk≥21

### I8: --tries=3 死配置 + retry 双重 decrement 风险
- **修复**: 明确"失败即终态"策略，删除 `--tries=3` 或保留 `failed()` 仅作日志；decrement 移到终态确认点

## Functional Requirements (修复后行为)

- **FR-F1**: 并发计数使用 Redis Lua 原子操作，无可绕过的竞态窗口
- **FR-F2**: appSecret 在整个生命周期（DB / APK manifest / API 响应）中均不出现在明文
- **FR-F3**: 注入后 APK 通过 task_token 向平台换取短期 JWT，再用 JWT 调用卡密验证接口
- **FR-F4**: 平台签名 keystore 不在 Git 仓库、不在镜像内，运行时挂载
- **FR-F5**: 生产环境 Java 微服务容器运行在 gVisor 沙箱中
- **FR-F6**: 超时 PROCESSING 任务（>15 分钟）被定时任务自动标记失败并回收并发计数
- **FR-F7**: PENDING 任务超 10 分钟未 dispatch 被回收，不占用并发额度
- **FR-F8**: Java 微服务校验 APK 魔数与 ZIP 炸弹，拒绝异常文件
- **FR-F9**: dispatch 时校验 MinIO 文件存在性与大小一致性
- **FR-F10**: MinIO lifecycle 规则按前缀生效，临时文件 1h / 输出 7d 自动清理
- **FR-F11**: 注入后 APK 启动时弹出卡密输入 UI，校验通过后才进入主应用
- **FR-F12**: KamiProxyApplication 位于主 dex，minSdk<21 宿主不崩溃

## Non-Functional Requirements

- **NFR-F1**: 并发计数操作均为 O(1) Redis 原子操作
- **NFR-F2**: task_token 一次性使用，JWT 有效期 ≤1 小时，支持刷新
- **NFR-F3**: 定时任务每 5 分钟执行一次，单次扫描 ≤1000 条
- **NFR-F4**: ZIP 炸弹检测阈值：未压缩总大小 ≤500MB，压缩比 ≤100:1
- **NFR-F5**: 所有修复不破坏现有 IDOR 防护、SQL 参数化、XXE 防护等已验证良好的逻辑

## Constraints
- **兼容性**: 不破坏现有卡密验证 API（verify/activate/heartbeat）的 HMAC 鉴权；新增 JWT 鉴权为并行支持
- **数据库**: 不破坏现有表结构，新增字段通过迁移
- **部署**: gVisor 需宿主机安装 runsc，文档需说明

## Acceptance Criteria

### AC-F1: 并发限制原子性
- **Given**: 商户已有 2 个进行中的注入任务
- **When**: 10 个并发请求同时提交第 3 个任务
- **Then**: 有且仅有 1 个请求成功，其余返回"并发超限"
- **Verification**: `programmatic`（并发测试脚本）

### AC-F2: appSecret 不落地
- **Given**: 任意注入任务
- **When**: 检查 DB `sdk_config` 列、APK manifest、detail API 响应
- **Then**: 三处均不含明文 app_secret；DB 只存 task_token；manifest 只含 task_token
- **Verification**: `programmatic`

### AC-F3: task_token 换取 JWT
- **Given**: 注入后 APK 首次启动
- **When**: SDK 用 task_token 调用 `/api/v1/sdk/auth`
- **Then**: 返回短期 JWT（含 app_key，不含 app_secret），有效期 1 小时
- **Verification**: `programmatic`

### AC-F4: keystore 不在仓库
- **Given**: 代码仓库
- **When**: `git log --all -- '**/platform.keystore'`
- **Then**: 无记录（已从历史移除）；运行时通过挂载注入
- **Verification**: `programmatic`

### AC-F5: gVisor 启用
- **Given**: 生产环境部署
- **When**: `docker inspect card-auth-apk-inject --format '{{.HostConfig.Runtime}}'`
- **Then**: 返回 `runsc`
- **Verification**: `programmatic`

### AC-F6: 卡死任务恢复
- **Given**: 一个 PROCESSING 任务 updated_at 为 20 分钟前
- **When**: 定时任务执行
- **Then**: 任务被标记为 FAILED，并发计数被回收
- **Verification**: `programmatic`

### AC-F7: 放弃上传回收
- **Given**: 商户创建任务后 11 分钟未调用 dispatch
- **When**: 回收定时任务执行
- **Then**: 任务被标记为 FAILED（"上传超时"），并发计数未增加（因 INCR 移到 dispatch）
- **Verification**: `programmatic`

### AC-F8: ZIP 炸弹防护
- **Given**: 上传一个压缩比 200:1 的恶意 ZIP
- **When**: Java 微服务处理
- **Then**: 任务失败，错误信息提示"疑似 ZIP 炸弹"
- **Verification**: `programmatic`

### AC-F9: 卡密校验 UI
- **Given**: 注入后 APK 启动
- **When**: 进入主 Activity 前
- **Then**: 弹出卡密输入界面，输入有效卡密后才进入主应用
- **Verification**: `human-judgement`

### AC-F10: checklist 与代码一致
- **Given**: 修复完成
- **When**: 对照 checklist.md 每一项检查代码
- **Then**: 所有标记 [x] 的检查点与代码事实一致
- **Verification**: `programmatic`

## Open Questions
- [ ] task_token 是否需要支持吊销（商户删除应用时）？
- [ ] JWT 刷新机制：SDK 如何在 JWT 过期后无感刷新？
- [ ] gVisor 在开发环境是否也要启用（影响调试便利性）？
- [ ] 卡密校验 UI 是否需要支持"试用模式"（允许 N 次免校验使用）？
