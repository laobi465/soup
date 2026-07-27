# review-fixes-v4 — 验证清单

## Task 1: 设备指纹增强（FR-1 / M2）
- [ ] CP-1.1: `KamiProxyApplication.java` 中 `getDeviceFingerprint()` 不再引用 `android.os.Build.SERIAL`
- [ ] CP-1.2: 方法内使用 `Settings.Secure.ANDROID_ID` + `Build.FINGERPRINT` + `Build.MODEL` + `Build.MANUFACTURER`
- [ ] CP-1.3: 输出为 64 字符 SHA-256 hex（`String.format("%02x", b)` 拼接）
- [ ] CP-1.4: 异常分支有 try-catch，失败时回退到 `Build.FINGERPRINT` 不崩溃
- [ ] CP-1.5: `grep -r "Build.SERIAL" apk-inject-service/sdk-module/` 无结果

## Task 2: Java 错误信息脱敏（FR-2 / M5）
- [ ] CP-2.1: `ApkInjectService.java` catch 块调用 `sanitizeError(e)` 而非 `e.getMessage()`
- [ ] CP-2.2: `sanitizeError` 方法存在且覆盖 5 类异常（ZIP/魔数、加固、minSdk、aapt2/apksigner/zipalign/APKEditor、兜底）
- [ ] CP-2.3: `log.error(..., e)` 保留完整堆栈写入服务端日志
- [ ] CP-2.4: 返回给商户的 error 字段不含 `/tmp/apk_inject_`、`aapt2 dump`、`apksigner`、`java.lang.RuntimeException` 等内部信息
- [ ] CP-2.5: 加固检测分支（L111-118）保持原有脱敏文案 `"检测到加固(...)，暂不支持"`

## Task 3: 入参校验加强（FR-3 / M6）
- [ ] CP-3.1: `sha256` 校验 `/^[a-f0-9]{64}$/i`，非法值返回 400
- [ ] CP-3.2: `filename` 长度校验 1-255，非法值返回 400
- [ ] CP-3.3: `filename` 字符校验 `/^[\p{L}\p{N}\.\-_() ]+$/u`，含路径分隔符 `/`、`\` 或特殊字符时返回 400
- [ ] CP-3.4: `file_size` 下限校验 `< 1024` 返回 400
- [ ] CP-3.5: `file_size` 上限校验 `> 104857600` 保持不变
- [ ] CP-3.6: `php -l ApkInjectController.php` 语法验证通过

## Task 4: 下载限流（FR-4 / M8）
- [ ] CP-4.1: `getDownloadUrl` 开头读取 Redis 计数 `apk_inject:dl_limit:{merchant_id}`
- [ ] CP-4.2: 计数 ≥ 10 时抛 `RuntimeException('下载链接获取过于频繁，请稍后再试')`
- [ ] CP-4.3: 使用 Lua 脚本原子 INCR + 首次 EXPIRE 3600s
- [ ] CP-4.4: 限流检查在任务查询之前（快速失败，不查库）
- [ ] CP-4.5: `php -l ApkInjectService.php` 语法验证通过

## Task 5: 残留 keystore 清理（FR-5）
- [ ] CP-5.1: `apk-inject-service/src/main/resources/keystore/platform.keystore` 文件已删除
- [ ] CP-5.2: `ls apk-inject-service/src/main/resources/keystore/` 目录为空或不存在
- [ ] CP-5.3: `grep -n "keystore" apk-inject-service/Dockerfile` 仅出现注释行（不 COPY keystore）
- [ ] CP-5.4: `docker-compose.yml` 中 `./deploy/keystore:/opt/keystore:ro` 挂载保持不变
- [ ] CP-5.5: `git status` 显示该文件删除（或无变化，因已被 .gitignore 忽略）

## Task 6: 语法验证 + 提交推送
- [ ] CP-6.1: `php -l server/app/controller/merchant/ApkInjectController.php` 输出 "No syntax errors"
- [ ] CP-6.2: `php -l server/app/service/ApkInjectService.php` 输出 "No syntax errors"
- [ ] CP-6.3: Java 代码无语法错误（`mvn compile` 通过或人工审查通过）
- [ ] CP-6.4: git commit 包含所有 5 个修复任务的变更
- [ ] CP-6.5: git push 到 origin main 成功

## 整体回归
- [ ] RG-1: 前三轮修复（C1-C7, I1-I8）未受影响（grep 确认关键代码仍在）
- [ ] RG-2: docker-compose.yml / docker-compose.prod.yml 未被意外修改
- [ ] RG-3: 评审报告中所有 Critical/Important 问题仍处于已修复状态
