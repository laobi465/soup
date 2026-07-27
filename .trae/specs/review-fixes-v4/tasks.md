# review-fixes-v4 — 任务清单

## [ ] Task 1: 设备指纹增强（FR-1 / M2）
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [KamiProxyApplication.java](file:///workspace/apk-inject-service/sdk-module/src/main/java/com/cardauth/sdk/KamiProxyApplication.java)
  - 重写 `getDeviceFingerprint()` 方法（当前在 L259-261）：
    ```java
    // 当前实现（废弃）:
    private String getDeviceFingerprint() {
        return android.os.Build.FINGERPRINT + "_" + android.os.Build.SERIAL;
    }
    ```
  - 改为：
    ```java
    private String getDeviceFingerprint() {
        try {
            String androidId = android.provider.Settings.Secure.getString(
                    getContentResolver(),
                    android.provider.Settings.Secure.ANDROID_ID);
            if (androidId == null) androidId = "";
            String raw = androidId
                    + "|" + android.os.Build.FINGERPRINT
                    + "|" + android.os.Build.MODEL
                    + "|" + android.os.Build.MANUFACTURER;
            java.security.MessageDigest md = java.security.MessageDigest.getInstance("SHA-256");
            byte[] hash = md.digest(raw.getBytes(java.nio.charset.StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder();
            for (byte b : hash) sb.append(String.format("%02x", b));
            return sb.toString();
        } catch (Exception e) {
            Log.e(TAG, "getDeviceFingerprint failed", e);
            return android.os.Build.FINGERPRINT;
        }
    }
    ```
  - 说明：`ANDROID_ID` 在 API 26+ 按应用签名级稳定（卸载重装不变，不同签名不同值），跨设备唯一性远优于废弃的 `Build.SERIAL`

## [ ] Task 2: Java 错误信息脱敏（FR-2 / M5）
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [ApkInjectService.java](file:///workspace/apk-inject-service/src/main/java/com/cardauth/inject/service/ApkInjectService.java)
  - 在 catch 块（L150-154）中，把 `response.setError(e.getMessage())` 改为 `response.setError(sanitizeError(e))`
  - 新增 `sanitizeError(Throwable e)` 私有方法：
    ```java
    private String sanitizeError(Throwable e) {
        String msg = e.getMessage() != null ? e.getMessage() : "";
        String cls = e.getClass().getSimpleName();
        // 按异常类型/消息特征映射到白名单分类
        if (msg.contains("ZIP 炸弹") || msg.contains("魔数") || msg.contains("ZIP炸弹")) {
            return "APK 文件校验失败";
        }
        if (msg.contains("加固") || msg.contains("shell")) {
            return "检测到加固，暂不支持";
        }
        if (msg.contains("minSdk") || msg.contains("minSdkVersion")) {
            return "APK 最低 SDK 版本不满足要求（需 API 21+）";
        }
        if (cls.contains("RuntimeException") && (msg.contains("aapt2")
                || msg.contains("apksigner") || msg.contains("zipalign")
                || msg.contains("APKEditor"))) {
            return "APK 解析或签名失败";
        }
        return "注入处理失败，请重试或联系客服";
    }
    ```
  - 保留 `log.error("inject failed, taskId={}", request.getTaskId(), e)` 完整堆栈写入服务端日志
  - 加固检测分支（L111-118）的 `response.setError(...)` 已是脱敏文案，保持不变

## [ ] Task 3: 入参校验加强（FR-3 / M6）
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [ApkInjectController.php](file:///workspace/server/app/controller/merchant/ApkInjectController.php)
  - 在 `create` 方法（L23-34）中加强校验：
    ```php
    $appId = (int) $request->post('app_id', 0);
    $filename = (string) $request->post('filename', '');
    $fileSize = (int) $request->post('file_size', 0);
    $sha256 = (string) $request->post('sha256', '');

    if (empty($appId) || empty($filename) || empty($sha256)) {
        return error('参数不完整', 400);
    }

    // M6: sha256 必须为 64 字符 hex
    if (!preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
        return error('sha256 格式无效（需 64 位十六进制）', 400);
    }

    // M6: filename 长度 1-255，仅允许安全字符
    $filenameLen = mb_strlen($filename);
    if ($filenameLen < 1 || $filenameLen > 255) {
        return error('文件名长度需在 1-255 字符之间', 400);
    }
    if (!preg_match('/^[\p{L}\p{N}\.\-_() ]+$/u', $filename)) {
        return error('文件名包含非法字符', 400);
    }

    // M6: file_size 下限 1KB，上限 100MB
    if ($fileSize < 1024) {
        return error('文件大小过小（需大于 1KB）', 400);
    }
    if ($fileSize > 104857600) {
        return error('文件大小超过限制（100MB）', 400);
    }
    ```

## [ ] Task 4: 下载限流（FR-4 / M8）
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [ApkInjectService.php](file:///workspace/server/app/service/ApkInjectService.php)
  - 在 `getDownloadUrl` 方法（L174-188）开头增加限流检查：
    ```php
    public function getDownloadUrl(int $taskId, int $merchantId): string
    {
        // M8: 商户级下载限流（10 次/小时）
        $redis = Cache::store('redis')->handler();
        $limitKey = 'apk_inject:dl_limit:' . $merchantId;
        $count = (int) $redis->get($limitKey);
        if ($count >= 10) {
            throw new \RuntimeException('下载链接获取过于频繁，请稍后再试');
        }
        // 原子 INCR，首次设置 EXPIRE
        $lua = <<<'LUA'
local c = redis.call('INCR', KEYS[1])
if c == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return c
LUA;
        $redis->eval($lua, [$limitKey, 3600], 1);

        $task = ApkInjectTask::where('id', $taskId)->where('merchant_id', $merchantId)->find();
        // ... 原有逻辑不变
    }
    ```

## [ ] Task 5: 残留 keystore 清理（FR-5）
- **Priority**: low
- **Depends On**: None
- **Description**:
  - 删除 `apk-inject-service/src/main/resources/keystore/platform.keystore`（2748 字节，已 gitignore 但文件残留）
  - 确认 [Dockerfile](file:///workspace/apk-inject-service/Dockerfile) 不引用该路径（已通过 `COPY src/main/resources/dex` 仅拷贝 dex，不拷贝 keystore）
  - 确认运行时 keystore 通过 `deploy/keystore` volume 挂载到 `/opt/keystore`（已在 docker-compose.yml 配置）

## [ ] Task 6: 语法验证 + 提交推送
- **Priority**: high
- **Depends On**: Task 1, 2, 3, 4, 5
- **Description**:
  - PHP 语法验证：`php -l server/app/controller/merchant/ApkInjectController.php`、`php -l server/app/service/ApkInjectService.php`
  - Java 编译验证：`cd apk-inject-service && mvn compile -q`（若环境无 mvn 则跳过，仅做语法审查）
  - 提交并推送到 GitHub main 分支
  - 提交信息：`fix(apk-inject): 收尾修复 M2/M5/M6/M8 + 清理残留 keystore`
