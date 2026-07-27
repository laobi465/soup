# review-fixes-v4 — APK 注入功能收尾修复（Minor 批量）

## Overview
- **Summary**: 修复第三轮全面审查后**仍残留的 5 个 Minor 级问题**，全部集中在 APK 注入功能的边缘加固：设备指纹增强、错误信息脱敏、入参校验加强、下载限流、残留 keystore 文件清理。
- **Purpose**: 前三轮（review-fixes / review-fixes-v3）已修复全部 7 Critical + 8 Important 问题（并发原子性、appSecret 泄露、keystore 入库、gVisor 沙箱、卡死任务回收、ZIP 炸弹检测、MinIO lifecycle、MultiDex 崩溃等）。本轮为**最后一轮收尾**，消除评审报告 M2/M5/M6/M8 四项 Minor 遗留 + 一处残留文件，使 APK 注入功能达到可上线状态。

## Background
APK 云端注入功能经三轮修复后，核心安全与可靠性问题已全部解决。本轮聚焦评审报告中明确标注但尚未处理的 Minor 项，属于"锦上添花"的防御性加固，不涉及架构变更。

## Scope
### In Scope
- **M2**: `KamiProxyApplication.getDeviceFingerprint()` 替换废弃的 `Build.SERIAL`，改用 ANDROID_ID + 多维特征哈希
- **M5**: Java 注入服务错误信息脱敏，按白名单分类返回给商户（不再泄露 tempDir/aapt2 原始输出/堆栈）
- **M6**: `ApkInjectController.create` 入参校验加强（sha256 格式、filename 长度/字符、file_size 下限）
- **M8**: `ApkInjectService.getDownloadUrl` 增加商户级限流（Redis 计数，10 次/小时）
- **清理**: 删除残留的 `apk-inject-service/src/main/resources/keystore/platform.keystore` 文件

### Out of Scope
- 已在 v1-v3 修复的 Critical/Important 问题（不重复）
- 业务逻辑重构、性能优化、文档完善
- gVisor 运行时强制启用（保持可选 + seccomp 兜底策略）

## Functional Requirements
### FR-1: 设备指纹增强（M2）
- `KamiProxyApplication.getDeviceFingerprint()` 不再使用 `Build.SERIAL`（API 26+ 废弃，返回 "unknown"）
- 改用 `Settings.Secure.ANDROID_ID`（应用签名级稳定）+ `Build.FINGERPRINT` + `Build.MODEL` + `Build.MANUFACTURER` 拼接后 SHA-256 哈希
- 输出固定 64 字符 hex，保证跨设备唯一性与同设备稳定性

### FR-2: 错误信息脱敏（M5）
- Java `ApkInjectService.inject()` 的 catch 块不再把 `e.getMessage()` 原样返回给商户
- 新增 `sanitizeError(Throwable)` 方法，按异常类型映射到白名单分类：
  - ZIP/魔数异常 → `"APK 文件校验失败"`
  - aapt2/apksigner/zipalign/APKEditor 异常 → `"APK 解析或签名失败"`
  - 加固检测 → `"检测到加固，暂不支持"`
  - minSdk 不足 → `"APK 最低 SDK 版本不满足要求（需 API 21+）"`
  - 其他 → `"注入处理失败，请重试或联系客服"`
- 原始异常仍写入服务端日志（`log.error` 保留完整堆栈），仅对商户响应脱敏
- PHP 端 `ApkInjectJob` 存储脱敏后的 error_log（不再含 tempDir/堆栈）

### FR-3: 入参校验加强（M6）
- `sha256`: 必须为 64 字符 hex（`/^[a-f0-9]{64}$/i`）
- `filename`: 长度 1-255，仅允许字母/数字/中文/`.-_()` 等安全字符
- `file_size`: 下限 1024 字节（1KB，拒绝空文件），上限保持 100MB

### FR-4: 下载限流（M8）
- `getDownloadUrl` 增加 Redis 计数限流：单商户 10 次/小时
- 超限返回 `"下载链接获取过于频繁，请稍后再试"`
- 限流 key: `apk_inject:dl_limit:{merchant_id}`，TTL 3600s

### FR-5: 残留 keystore 清理
- 删除 `apk-inject-service/src/main/resources/keystore/platform.keystore`（2748 字节，已 gitignore 但文件残留）
- 确认 Dockerfile 不引用该路径（已通过 volume 挂载 `deploy/keystore`）

## Non-Functional Requirements
- **NFR-1**: 所有修改不破坏现有功能，PHP 语法验证通过、Java 编译通过
- **NFR-2**: 脱敏后的错误分类必须覆盖所有已知异常路径，不遗漏
- **NFR-3**: 限流计数器使用 Redis 原子 INCR + EXPIRE，避免竞态

## Acceptance Criteria
- [ ] `KamiProxyApplication.getDeviceFingerprint()` 不再引用 `Build.SERIAL`
- [ ] Java 注入服务返回给商户的 error 字段不含 tempDir 路径、aapt2 原始输出、堆栈信息
- [ ] `ApkInjectController.create` 对 sha256/filename/file_size 做格式校验，非法输入返回 400
- [ ] `getDownloadUrl` 单商户第 11 次调用返回限流错误
- [ ] `apk-inject-service/src/main/resources/keystore/` 目录下无 keystore 文件
- [ ] `php -l` 与 `mvn compile` 均通过
