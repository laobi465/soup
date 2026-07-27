# APK 云端注入功能 — 代码评审报告

- **评审范围**：`a966680..d950116`（APK Cloud Injection 全量 diff）
- **评审日期**：2026-07-27
- **评审人**：高级代码评审员
- **HEAD 说明**：HEAD 提交 `d950116` 实为「安装 GitHub 仓库」，功能代码集中在 `63da0e5`。所谓"post-review fix commit"在 diff 中未见实质性修复痕迹（race condition、密钥泄露等仍存在）。

---

## 一、Strengths（做得好的地方）

- **职责分层清晰**：PHP 仅编排（生成 presigned URL、写任务表、投队列），Java 微服务执行注入流水线，前端浏览器直传 MinIO，不占用 PHP-FPM worker（满足 NFR-2）。
- **IDOR 防护到位**：`ApkInjectService` 的 `getList/getDetail/getDownloadUrl/dispatchTask` 全部按 `merchant_id` 过滤；`createTask` 校验 `app_id` 归属（`app/service/ApkInjectService.php:26,102,118,156`）。
- **SQL 注入防护**：全部使用 ThinkPHP 查询构建器参数绑定，无原生 SQL 拼接。
- **XXE 防护**：`ManifestModifier` 关闭了 DOCTYPE/外部实体（`ManifestModifier.java:85`）。
- **DexMerger 细节正确**：保留 STORED 条目原始压缩方式（`.so`/`resources.arsc`），丢弃旧签名文件，对 SDK dex 做魔数校验（`DexMerger.java:86-95,118-129`）。
- **外部工具超时控制**：APKEditor 10 分钟、apksigner 5 分钟超时 + `destroyForcibly`（`ManifestModifier.java:187-192`、`ApkSigner.java:50-54`）。
- **Java 临时目录清理**：`finally` 中递归删除临时目录（`ApkInjectService.java:148-152,192-206`）。
- **生产容器加固（部分）**：`docker-compose.prod.yml` 对 inject 服务配置了 `read_only`、`tmpfs /tmp`、`cap_drop: ALL`、`no-new-privileges`（`164-170`）。
- **前端 SHA-256 客户端计算**：减轻服务端负担，去重前置（`create.vue:101-108`）。
- **DOM 改写而非文本替换**：Manifest 修改用 DOM API，比正则替换稳健。

---

## 二、Critical Issues（必须在上线前修复）

### C1 — 并发限制存在 check-then-act 竞态（前轮评审发现的问题 **未修复**）

- **文件**：`server/app/service/ApkInjectService.php:32-37, 85-87`
- **问题**：并发限制采用「先 `GET` 读当前值判断 ≥3，再在末尾 `INCR`」的两步操作：

```php
$current = (int) $redis->get($concurrentKey);          // 第 34 行
if ($current >= self::MAX_CONCURRENT) { throw ...; }   // 第 35 行
// …中间隔了 50 行业务（应用校验、去重查询、写库、生成 presigned URL）…
$redis->incr($concurrentKey);                           // 第 86 行
```

两个并发请求可同时读到 `current=2`、同时通过校验、同时 `INCR` 到 4，**突破 MAX_CONCURRENT=3 限制**。前轮评审已指出该问题，但当前代码仍未用 Lua 脚本或「INCR 后溢出回退 DECR」的原子方式修复。`checklist.md` Checkpoint 26「并发限制使用 Redis 原子计数」为不实自评。
- **严重性**：Critical（可被绕过的反滥用限制 + 计数器失真）
- **建议**：用 Lua 原子「INCR→若 >MAX 则 DECR 并返回失败」：

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
$current = $redis->eval($lua, [$concurrentKey, self::MAX_CONCURRENT, 7200], 1);
if ($current < 0) { throw new \RuntimeException('并发任务数超限…'); }
```
将计数前移到任务记录创建之前，失败路径不会再泄漏计数。

---

### C2 — appSecret 以明文写入 APK 的 AndroidManifest meta-data

- **文件**：`apk-inject-service/.../service/ManifestModifier.java:107-109`；运行时读取见 `KamiProxyApplication.java:91`
- **问题**：注入时把 `app_key/app_secret/base_url` 作为明文 `<meta-data>` 写入 AndroidManifest：

```java
insertMetaData(doc, application, "kami_app_key",    nullToEmpty(request.getAppKey()));
insertMetaData(doc, application, "kami_app_secret", nullToEmpty(request.getAppSecret()));
insertMetaData(doc, application, "kami_base_url",   nullToEmpty(request.getBaseUrl()));
```

`appSecret` 是商户调用卡密 API 的**签名密钥**（`CardAuthClient` 用它做 `SignUtil.sign`，见 `CardAuthClient.java:61`）。任何拿到注入后 APK 的人用 `aapt2 dump xmltree` 或反编译即可提取明文 `kami_app_secret`，进而伪造商户身份调用 verify/activate/heartbeat。APK 是分发给终端用户的，等同于把商户主密钥公开。
- **严重性**：Critical（商户凭证泄露 → 整个应用鉴权体系被绕过）
- **建议**：不要把 appSecret 落地到 APK。可选方案：
  1. 注入时只为 APK 绑定一个不可派生的一次性 `app_key`（或注入任务 token），运行时由 SDK 携带该 token 向平台换取临时会话凭证；
  2. 或对 appSecret 做白盒/混淆 + 设备绑定下发，至少不能是 manifest 明文。
  无论哪种，manifest 中绝不应出现可被直接复用的签名密钥。

---

### C3 — appSecret 明文落库，且经 detail 接口泄露给前端

- **文件**：`server/app/service/ApkInjectService.php:56-69`（明文写库）、`ApkInjectService.php:154-161`（detail 原样返回）、`server/app/model/ApkInjectTask.php:16`（`sdk_config` JSON 字段）
- **问题**：`createTask` 解密 `app_secret_encrypted` 后，把明文 `app_secret` 拼进 `sdk_config` JSON 写入 `apk_inject_tasks.sdk_config` 列：

```php
$sdkConfig = json_encode([
    'app_key' => $app->app_key,
    'app_secret' => $plainSecret,   // 明文
    'base_url' => ...,
]);
```

随后 `getDetail()` 直接 `return $task->toArray();`，`sdk_config` 是模型 JSON 字段，会被序列化进接口响应。即 `GET /api/merchant/apk-inject/detail/:id` 响应体里**包含明文 app_secret**（即便前端 `index.vue` 详情弹窗未展示，网络响应仍含密文）。任何能查看任务详情的商户子账号、抓包中间盒、日志采集都能拿到。
- **严重性**：Critical（凭证明文持久化 + API 泄露）
- **建议**：
  1. 不要把明文 secret 持久化到任务表；任务表只存 `app_id`，Job 执行时再从 `App` 模型解密取用、用后即焚。
  2. `getDetail` 返回前必须 `unset($data['sdk_config'])` 或用 `$hidden` 屏蔽。
  3. 模型里加 `protected $hidden = ['sdk_config'];`。

---

### C4 — 平台签名 keystore 被提交到 Git 仓库，且密码为默认值 `changeit`

- **文件**：`apk-inject-service/src/main/resources/keystore/platform.keystore`（已入库，blob `28ff378…`，2748 字节）；`application.yml:29` 与 `docker-compose.yml:122` 默认 `APK_KEYSTORE_PASSWORD: changeit`
- **问题**：所有注入后的 APK 都用同一把"平台统一签名"（spec Non-Goals 已声明 MVP 用统一签名）。该 keystore 文件直接 commit 进仓库，密码在配置里硬编码为 `changeit`。任何拿到代码仓库的人都能用同一把 key 签名任意 APK，从而：
  - 冒充平台对所有注入应用签名；
  - 若同一 keystore 将来被复用到其它高权限应用，可被用于伪造安装包。
  此外 `apk-inject-service/Dockerfile:35` 把 keystore 直接 COPY 进镜像，镜像分发同样泄露。
- **严重性**：Critical（签名私钥泄露 → 供应链冒名）
- **建议**：
  1. 立即从 Git 历史中移除 `platform.keystore`（`git filter-repo`），并轮换证书；
  2. keystore 通过 Docker secret / 挂载卷在运行时注入，不进镜像、不进仓库；
  3. 密码强制要求环境变量 `APK_KEYSTORE_PASSWORD`（去掉 `:-changeit` 默认值），并加入 `.example.env` 说明。

---

### C5 — gVisor 沙箱未启用（NFR-3 未满足），且自评清单/提交信息谎称已配置

- **文件**：`docker-compose.prod.yml:171-172`（`# runtime: runsc` 被注释）；`docker-compose.yml` 完全没有相关配置
- **问题**：NFR-3 明确要求 "Java 微服务运行在 gVisor 沙箱容器中，隔离不可信 APK"。实际 `runtime: runsc` 被注释掉，容器以默认 runc 运行。该容器要执行 `aapt2 dump`、`zipalign`、`apksigner`、`java -jar APKEditor.jar` 等外部工具解析/重打包**攻击者可控的 APK**，一旦这些工具存在解析漏洞（APKEditor/ARSCLib 历史上有过），即可逃逸到宿主机。同时 `checklist.md` Checkpoint 59「生产环境配置 gVisor (runtime: runsc)」与提交信息「gVisor沙箱」均为不实陈述。
- **严重性**：Critical（不可信输入处理容器无强隔离 → 远程代码执行逃逸面）
- **建议**：
  1. 生产宿主机安装 `runsc`，在 `docker-compose.prod.yml` 取消注释 `runtime: runsc`；
  2. 若运行时不支持 gVisor，至少补充 seccomp Profile 限制系统调用、并禁止容器内提权；
  3. 修正 checklist 与提交描述，避免误信。

---

### C6 — Worker 崩溃后任务永久卡在"处理中"，并发计数永不回收

- **文件**：`server/app/job/ApkInjectJob.php:16-79`；无任何看门狗/兜底回收
- **问题**：任务状态从 `PROCESSING` → 终态的转换完全依赖 `fire()` 内 `try/catch/finally` 正常执行。但以下场景 `finally` 不会执行、`failed()` 也不会被调用：
  - PHP queue worker 进程被 OOM Killer 杀死（`mem_limit` 未设，见 M4）或 SIGKILL；
  - Guzzle 调用 Java 微服务时 PHP 进程崩溃；
  - `callInjectService` 抛 `Error`（非 `Exception`）时 `finally` 会执行，但若进程级被杀则不会。

  结果：任务停在 `status=2` 永不流转，`decrementConcurrent` 不触发 → 该商户并发计数卡住，2 小时内无法新建任务（`CONCURRENT_KEY` expire 7200s）。spec NFR-4 只提了临时文件清理，未覆盖卡死任务。
- **严重性**：Critical（可用性：单商户被永久阻塞；状态不一致）
- **建议**：
  1. 增加定时任务（`php think schedule:run`）扫描 `status=PROCESSING AND updated_at < NOW() - 15 分钟` 的任务，标记为失败并 `decrementConcurrent`；
  2. 或在 `createTask` 入口用 Redis 计数 + DB 实际 `PROCESSING` 计数双向校准，发现漂移时自愈；
  3. 给 worker 容器设 `mem_limit` 防止被 OOM 直接杀进程。

---

### C7 — 并发计数在"创建任务"时就 +1，但只在"任务执行结束"时 -1，导致放弃上传的任务泄漏计数

- **文件**：`server/app/service/ApkInjectService.php:86`（创建即 INCR）、`ApkInjectJob.php:75`（仅 job 结束才 DECR）
- **问题**：流程是「createTask（INCR）→ 前端直传 MinIO → dispatchTask（投队列）→ Worker fire（DECR）」。`createTask` 一返回就 +1，但：
  - 用户拿到 presigned URL 后**从未上传**或**未调用 dispatch**，任务永远停在 `PENDING`，Worker 永不执行 → 计数永不 -1；
  - 上传失败、网络中断同样如此。
  该计数仅在 2 小时（`expire 7200`）后自动消失。期间商户创建 3 个放弃任务后即被锁死。
- **严重性**：Critical（可用性：正常使用模式下即可触发商户被锁）
- **建议**：
  - 把 INCR 移到 `dispatchTask`（真正投队列时）或 `fire()` 进入处理时；`createTask` 只做记录；
  - 或在 `createTask` 后给一个短 TTL（如 10 分钟）的"待上传"票据，dispatch 时转为正式并发计数；
  - 对 `PENDING` 超过 10 分钟未 dispatch 的任务做回收 + 计数回退。

---

## 三、Important Issues（应在上线前修复）

### I1 — 缺少 APK 真实性校验与 ZIP 炸弹防护（FR-12 未实现）

- **文件**：`server/app/controller/merchant/ApkInjectController.php:23-34`（仅校验扩展名/声明大小）；`DexMerger.java:140-144`（`readAllBytes` 全量入内存）
- **问题**：
  1. 上传仅靠前端 `accept=".apk"` 和客户端声明的 `file_size`，服务端不校验文件魔数（`PK\x03\x04`）也不是真正的 APK；
  2. `DexMerger.readAllBytes` 把每个 zip 条目**整块读进内存**，无解压比/总大小限制。一个压缩率极高的 ZIP 炸弹能让 2GB 容器 OOM；
  3. spec FR-12 明确要求"ZIP炸弹检测"，代码中无任何实现。
- **严重性**：Important（DoS / 资源耗尽）
- **建议**：在 Java 下载后校验 `PK\x03\x04` 魔数；遍历 entry 时累计未压缩大小，超过阈值（如 500MB）或压缩比 > 100:1 即拒绝；`DexMerger` 用流式复制而非全量 `readAllBytes`。

### I2 — `decrementConcurrent` 仍是 GET-then-check-then-DECR 的 TOCTOU 竞态

- **文件**：`server/app/service/ApkInjectService.php:166-181`
- **问题**：

```php
$current = (int) $redis->get($concurrentKey);
if ($current > 0) {
    $redis->decr($concurrentKey);
}
```

两个并发 decrement 可同时读到 `1`、同时通过 `>0`、同时 `DECR` → 结果落到 `-1`，导致后续并发额度虚高（计数器欠账）。本意是防负数，但非原子使其失效。
- **严重性**：Important（计数器漂移 → 并发限制逐步失效）
- **建议**：直接 `DECR`，若返回值 `<0` 则 `INCR` 回补；或用 Lua `local c=redis.call('DECR',KEYS[1]); if c<0 then redis.call('INCR',KEYS[1]) end return c`。

### I3 — dispatch 时不校验文件是否已上传 / 大小 / sha 是否与声明一致

- **文件**：`server/app/service/ApkInjectService.php:100-111`
- **问题**：`dispatchTask` 只校验任务状态即投队列，不向 MinIO `headObject` 确认文件存在，也不复核大小/SHA-256。恶意用户可：未上传就 dispatch（Java 下载失败，浪费一轮处理）；或上传一个与声明 `sha256/size` 不符的文件绕过去重。
- **严重性**：Important（去重可绕过 / 无效任务占用流水线）
- **建议**：`dispatchTask` 中 `headObject` 校验存在性与 `Content-Length` 是否等于 `file_size`；可选地由 Java 端在下载后计算 sha256 比对任务记录。

### I4 — MinIO lifecycle 规则作用对象与实际写入路径不一致，NFR-4/NFR-5 实际未生效

- **文件**：`docker-compose.yml:96-102`、`docker-compose.prod.yml:127-133`（init 脚本）；`ApkInjectService.php:51`（source 路径）；`ApkInjectService.java:135`（output 路径）
- **问题**：`minio-init` 创建的是**独立 bucket** `apk-source`/`apk-output`/`apk-temp`，并对这些独立 bucket 设置生命周期。但 PHP/Java 实际把文件写到**唯一 bucket `card-auth`** 内的 `apk-source/...`、`apk-output/...` **键前缀**下（`MinioStorage` 只用单一 `bucket` 配置）。生命周期规则作用在空 bucket `apk-source` 上，对 `card-auth` bucket 里的 `apk-source/` 前缀完全无效。结果：
  - NFR-4（临时文件 1h 清理）未生效；
  - NFR-5（输出 APK 7 天清理）未生效；
  - 源 APK、输出 APK 永久残留，扩容存储成本 + 数据留存风险。
- **严重性**：Important（合规/成本/数据留存）
- **建议**：二选一并统一：
  - 方案 A：PHP/Java 改为按 bucket 写入（`apk-source`/`apk-output` bucket），`MinioStorage` 支持按用途切换 bucket；
  - 方案 B：保留单 bucket，用 `mc ilm rule add --expire-hours 1 local/card-auth --prefix "apk-source/"` 按前缀配置生命周期。
  并在 init 脚本里 `mc ilm rule ls` 校验规则确实命中实际 key。

### I5 — `KamiProxyApplication.replaceWithOriginalApplication` 检查了错误的变量

- **文件**：`apk-inject-service/sdk-module/.../KamiProxyApplication.java:99-119`
- **问题**：

```java
private void replaceWithOriginalApplication() throws Exception {
    if (appKey == null || appKey.isEmpty()) {                 // ← 检查的是 appKey
        Log.w(TAG, "No original application class specified"); // ← 日志说的却是 app class
        return;
    }
    // …随后才去读 originalAppClass
```

判断条件用了 `appKey`，而日志和语义都是"原 Application 类名"。当 `app_key` 配置缺失时，会直接跳过 Application 替换（宿主原 Application 的 `attach/onCreate` 不被调用，业务初始化丢失），且日志误导排查方向。
- **严重性**：Important（注入后宿主 Application 不生效 → 业务初始化丢失）
- **建议**：先把 `originalAppClass` 读出来，再判断 `if (originalAppClass == null || originalAppClass.isEmpty() || originalAppClass.equals("android.app.Application"))` 决定是否替换；`appKey` 缺失应单独报警但不阻断 Application 替换。

### I6 — 注入后卡密校验实际不生效（verify 传空卡号，UI 未实现）

- **文件**：`KamiProxyApplication.java:172-194`（`client.verify("", ...)`，TODO 弹窗）
- **问题**：`startCardVerification` 用空字符串作为 `card_no` 调 `verify`，必然返回失败；校验失败分支仅 `Log.w` + `// TODO: 弹出卡密输入 UI`。也就是说注入后的 APK 既不弹卡密输入框、也不阻断使用，"卡密验证"功能名存实亡。这与功能核心目标"零源码接入卡密验证"直接冲突。
- **严重性**：Important（核心功能未达成）
- **建议**：至少实现 `CardVerifyActivity` 弹窗（SDK 里已有 `ui/CardVerifyActivity.java`，但 `KamiProxyApplication` 未拉起）；校验未通过时阻塞主 Activity。

### I7 — SDK 被放入 `classes(N+1).dex`，在 minSdk<21 且无 MultiDex 的宿主上启动即崩

- **文件**：`DexMerger.java:106-112`（追加为 `classes2.dex`）；`KamiProxyApplication.java:58-65`（MultiDex.install 依赖宿主含 androidx.multidex）
- **问题**：`KamiProxyApplication` 本身就在新追加的 `classes2.dex` 里，但它又是 manifest 声明的入口 Application。在 `minSdk < 21`（Dalvik）的宿主上，secondary dex 不会自动加载 → ClassLoader 找不到 `KamiProxyApplication` → **应用启动即 `ClassNotFoundException`**。`attachBaseContext` 里的 `MultiDex.install` 是鸡生蛋问题：要进入 `attachBaseContext` 必须先加载到该类，但该类恰在未安装的 secondary dex 里。即便 minSdk≥21（ART 原生多 dex）可工作，spec 未限制 minSdk，存在隐性崩溃面。Task 6 注释也声明"不含 AndroidX"，所以 `Class.forName("androidx.multidex.MultiDex")` 必然失败、仅打 warning。
- **严重性**：Important（部分宿主启动崩溃）
- **建议**：把 `KamiProxyApplication` 编进 `classes.dex`（主 dex），SDK 其余类放 secondary dex；或在注入前检查 `minSdk≥21`，否则拒绝并提示。

### I8 — `--tries=3` 实为死配置，`failed()` 回调几乎不会被触发，decrement 路径脆弱

- **文件**：`ApkInjectJob.php:73-79`（`finally` 后必 `$job->delete()`）、`ApkInjectJob.php:122-136`（`failed()`）、`docker-compose.yml:139`（`--tries=3`）
- **问题**：`fire()` 无论成功失败都在末尾 `$job->delete()`，永不 release，因此 `--tries=3` 永不生效、`failed()` 回调（think-queue 在重试耗尽时调用）实际是死代码。一旦将来有人把异常分支改成 `$job->release()` 触发重试，`finally` 里的 `decrementConcurrent` 会在**每次重试**都执行，而 `createTask` 只 INCR 一次 → 计数被多次扣减。当前虽未触发，但 retry 语义与 decrement 耦合极其脆弱。
- **严重性**：Important（潜在的双重 decrement + 误导性配置）
- **建议**：明确策略：要么"失败即终态"（删除 `--tries=3` 与 `failed()`，或保留 `failed()` 仅作日志）；要么支持重试，但把 decrement 从 `finally` 移到"终态确认点"（成功/最终失败），并用幂等键防止重复扣减。

---

## 四、Minor Issues（建议修复）

- **M1 — 自评清单与提交信息含不实陈述**：`checklist.md` Checkpoint 15（`uk_file_sha256` 唯一索引，实为非唯一 `idx_file_sha256`，而迁移用非唯一索引本身是对的）、Checkpoint 26（"原子计数"，实为竞态）、Checkpoint 59（"配置 gVisor"，实为注释）。建议清单与代码一致，避免评审误导。
- **M2 — 设备指纹弱**：`KamiProxyApplication.java:196-198` 用 `Build.FINGERPRINT + "_" + Build.SERIAL`，`Build.SERIAL` 自 API 26 起废弃、返回 `"unknown"`，导致跨设备指纹重复，影响一卡一机绑定。建议用 ANDROID_ID + 多维特征哈希。
- **M3 — 开发环境 `docker-compose.yml` 缺少生产加固项**：inject 服务未设 `read_only/tmpfs/cap_drop/no-new-privileges`（仅 prod 有）。开发环境跑不可信 APK 同样有风险，建议两份配置对齐。
- **M4 — `apk-queue-worker` 容器无资源限制**：`docker-compose.yml:135-148` 与 prod 均未设 `mem_limit/cpus`。Worker 处理大文件/长连接时无上限，可能拖垮宿主机（也与 C6 的 OOM 杀进程风险叠加）。
- **M5 — `error_log` 可能向商户泄露内部路径**：Java 服务把 `tempDir`、aapt2/apksigner 原始输出、堆栈写进 `error`，PHP 原样存 `error_log` 并经 detail 接口返回。建议对返回给商户的错误做白名单脱敏（如只返回"解析失败/加固/签名失败"等分类）。
- **M6 — 入参校验偏弱**：`sha256` 未校验为 64 位 hex（`ApkInjectController.php:26`）；`filename` 未做长度/字符过滤（虽仅入库不作为路径，风险低）；`file_size` 仅上限校验，无下限（0 字节也通过）。
- **M7 — aapt2 Application 名提取边界情况**：`ApkParser.java:80-91` 用 `name='([^']+)'` 在 `application:` 行首匹配，若应用 label 值里含 `name=` 子串会误匹配。概率低，建议改为精确匹配 `application:` 行内的 `name=` 属性。
- **M8 — 下载 presigned URL 每次重新生成**：`getDownloadUrl` 每次都签发新 URL（1h 有效），无频率限制，可被刷接口放大 MinIO 负载。建议加商户级限流。

---

## 五、针对评审要点的逐条核实结论

| # | 关注点 | 核实结论 |
|---|--------|----------|
| 1 | 并发原子性 | **未修复**。仍是 GET→check→INCR（C1）。 |
| 2 | 卡死任务恢复 | **无机制**。无看门狗/超时回收（C6）。 |
| 3 | 重复 decrement | finally+failed 路径脆弱，retry 时会重复扣减（I8）；decrement 本身也非原子（I2）。 |
| 4 | appSecret 传递 | **明文写 manifest meta-data**（C2），且明文落库+API 泄露（C3）。 |
| 5 | 签名 keystore | **提交进 Git + 默认密码 changeit**（C4）。Java 临时目录清理已做（OK）。 |
| 6 | presigned URL | 上传 5min/下载 1h 符合规格；URL 绑定具体 Key（OK）。但下载无频控（M8）。 |
| 7 | SHA-256 去重 | 按 (merchant_id, sha256) 去重，不同商户可重复——合理；但 dispatch 不复核实际 sha（I3），可绕过。 |
| 8 | 文件校验 | 仅扩展名+声明大小，无魔数/zip 炸弹校验（I1）。 |
| 9 | Java 错误处理 | finally 清理临时目录（OK）；但 error 信息原样回传商户（M5）。 |
| 10 | 容器资源/gVisor | mem/cpus/pids 有；**gVisor 被注释未启用**（C5）；dev 配置缺加固（M3）；worker 无限制（M4）。 |
| 11 | 入参校验 | merchant_id 走中间件、app_id 校验归属（OK）；sha256/filename 偏弱（M6）。 |
| 12 | SQL 注入/IDOR | 参数绑定 + merchant_id 过滤（OK）。 |

---

## 六、Assessment（总体结论）

**Not ready（不可上线）**

理由：存在 7 个 Critical 级问题，其中 C1（并发竞态未修复）、C2/C3（appSecret 明文泄露到 APK 与 DB/API）、C4（签名私钥入库）、C5（gVisor 未启用却在清单/提交信息中谎称已配置）、C6/C7（任务卡死与计数泄漏导致商户被锁）任意一项都构成上线阻断条件。此外 NFR-3（gVisor）、NFR-4/5（清理）、FR-12（ZIP 炸弹检测）、核心目标"卡密验证生效"（I6）均未达成。

**最低修复清单（上线前必做）**：
1. C1：并发计数改 Lua 原子 INCR+溢出回退。
2. C2/C3：appSecret 不得落地 APK manifest、不得明文落库、detail 接口屏蔽 `sdk_config`。
3. C4：keystore 移出仓库并轮换、密码强制环境变量。
4. C5：启用 `runtime: runsc`（或等价 seccomp），修正不实自评。
5. C6/C7：增加 PROCESSING 超时回收定时任务；并发计数改为在 dispatch/fire 时增减，回收放弃任务。
6. I1/I4：补 ZIP 炸弹/魔数校验；修正 MinIO lifecycle 作用对象。
7. I6：拉起卡密校验 UI，使核心功能真正生效。

修复完成后建议复审，重点回归并发计数原子性、密钥流转、容器沙箱与生命周期清理四条主线。
