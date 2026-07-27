# 全面审查一键修复 - 任务分解

## 任务依赖关系
```
Task 1 (C1 充值双记) → Task 8 (I2 退款校验)
Task 2 (C2 rebindDevice) ← 依赖 Task 6 (unbind_time migration)
Task 3 (C4 IP 黑名单) ← 独立
Task 4 (C5 IPv6) ← 独立
Task 5 (C6 APK 去重锁) ← 独立
Task 6 (unbind_time migration) ← 独立
Task 7 (I1 钱包锁) ← 依赖 Task 1
Task 8 (I2 退款校验) ← 依赖 Task 1
Task 9 (I3 缓存模型 exists) ← 独立
Task 10 (I4 封禁后校验) ← 独立
Task 11 (I5 JWT 黑名单验签) ← 独立
Task 12 (I6 彩虹支付白名单) ← 独立
Task 13 (I7 支付配置加密) ← 独立
Task 14 (I9 banCard 事务) ← 独立
Task 15 (Minor: mt_rand + Content-Length) ← 独立
Task 16 (语法验证 + 提交) ← 依赖所有
```

---

## [ ] Task 1: 充值统一走 wallet 账本 (FR-1.1 / C1)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/PaymentService.php:226-232](file:///workspace/server/app/service/PaymentService.php)
  - 移除 `handleNotify` 第 227-230 行对 `merchant.balance` 的直接修改
  - 充值统一由 `processRecharge` 更新 `wallet.balance` + 同步 `merchant.balance`
  - 修改要点：
    ```php
    // handleNotify 中移除:
    // if ($merchant && $order->type == Order::TYPE_RECHARGE) {
    //     $merchant->balance = bcadd(strval($merchant->balance), strval($order->amount), 2);
    //     $merchant->save();
    // }

    // processRecharge 中同步更新 merchant.balance (保持双字段一致):
    protected function processRecharge(Order $order): void
    {
        $merchant = Merchant::where('id', $order->merchant_id)->lock(true)->find();
        if (!$merchant) return;

        $wallet = Wallet::where('user_id', $merchant->user_id)
            ->where('type', 1)->lock(true)->find();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $merchant->user_id;
            $wallet->type = 1;
            $wallet->balance = 0;
            $wallet->frozen = 0;
        }

        $wallet->balance = bcadd(strval($wallet->balance), strval($order->amount), 2);
        $wallet->save();

        // 同步 merchant.balance (冗余字段, 保持与 wallet.balance 一致)
        $merchant->balance = $wallet->balance;
        $merchant->save();

        $transaction = new WalletTransaction();
        // ... 原有流水记录
    }
    ```
- **Verification**: 
  - 充值后 `merchant.balance == wallet.balance`
  - `wallet_transactions` 表仅 1 条流水

---

## [ ] Task 2: rebindDevice 事务+行锁+审计 (FR-1.2 / C2)
- **Priority**: critical
- **Depends On**: Task 6
- **Description**:
  - 修改 [server/app/service/CardService.php:876-939](file:///workspace/server/app/service/CardService.php)
  - 事务包裹 + Card/Device 加 `lock(true)`
  - 旧设备软删（`is_online=0`, `unbind_time`）+ 新建设备记录
  - 修改要点：
    ```php
    public static function rebindDevice(string $cardNo, int $appId, string $oldDevice, string $newDevice, string $deviceName = ''): array
    {
        $cardNoHash = self::hashCardNo($cardNo);

        Db::startTrans();
        try {
            $card = Card::where('card_no_hash', $cardNoHash)
                ->where('app_id', $appId)
                ->lock(true)
                ->find();

            if (!$card) {
                Db::rollback();
                return ['success' => false, 'code' => 4101, 'message' => '卡密不存在'];
            }
            if ($card->status != Card::STATUS_ACTIVATED) {
                Db::rollback();
                return ['success' => false, 'code' => 4102, 'message' => '卡密未激活'];
            }

            $oldDev = Device::where('card_id', $card->id)
                ->where('device_fingerprint', $oldDevice)
                ->lock(true)
                ->find();
            if (!$oldDev) {
                Db::rollback();
                return ['success' => false, 'message' => '旧设备不存在'];
            }

            $newDevExists = Device::where('card_id', $card->id)
                ->where('device_fingerprint', $newDevice)
                ->lock(true)
                ->find();
            if ($newDevExists) {
                Db::rollback();
                return ['success' => true, 'code' => 0, 'message' => '新设备已绑定',
                    'data' => ['device_id' => $newDevExists->id]];
            }

            // 旧设备软删 (保留审计链)
            $now = date('Y-m-d H:i:s');
            $oldDev->is_online = 0;
            $oldDev->unbind_time = $now;
            $oldDev->save();

            // 新建设备记录
            $newDev = new Device();
            $newDev->card_id = $card->id;
            $newDev->device_fingerprint = $newDevice;
            $newDev->device_name = $deviceName ?: $oldDev->device_name;
            $newDev->bind_time = $now;
            $newDev->last_heartbeat = $now;
            $newDev->is_online = 1;
            $newDev->save();

            Db::commit();
            self::clearCardCache($cardNoHash);

            return ['success' => true, 'code' => 0, 'message' => '换绑成功',
                'data' => ['device_id' => $newDev->id]];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
    ```
- **Verification**: 
  - 并发换绑只一个成功
  - 旧设备 `is_online=0`, `unbind_time` 有值
  - 新设备 `is_online=1`, `bind_time` 有值

---

## [ ] Task 3: Hash 路径 IP 黑名单 (FR-1.3 / C4)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/CardService.php:699-726](file:///workspace/server/app/service/CardService.php)
  - `recordBruteForceFailByHash` 检查 IP 失败次数达阈值后加入 IP 黑名单
  - 在 `ApiAuthMiddleware` 入口检查 IP 黑名单
  - 修改要点：
    ```php
    public static function recordBruteForceFailByHash(string $cardHash, string $ip): void
    {
        // ... 原有 IP 失败计数

        // IP 失败次数达阈值 → 加入黑名单
        if ($ipFailCount >= self::BRUTE_FORCE_IP_LIMIT) {
            self::banIp($ip, '暴力破解触发自动封禁');
        }
    }

    protected static function banIp(string $ip, string $reason): void
    {
        $existing = RiskBlacklist::where('type', 1)
            ->where('value', $ip)
            ->where(function($q) {
                $q->whereNull('expire_time')
                  ->whereOr('expire_time', '>', date('Y-m-d H:i:s'));
            })
            ->find();
        if (!$existing) {
            RiskBlacklist::create([
                'type' => 1,
                'value' => $ip,
                'reason' => $reason,
                'expire_time' => date('Y-m-d H:i:s', time() + 86400), // 24h
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    ```
- **Verification**: 
  - IP 失败次数达阈值后 `ca_risk_blacklist` 表有记录

---

## [ ] Task 4: ipInCidr 支持 IPv6 (FR-1.4 / C5)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [server/app/middleware/ApiAuthMiddleware.php:189-196](file:///workspace/server/app/middleware/ApiAuthMiddleware.php)
  - 实现 IPv4/IPv6 双栈支持
  - 修改要点：
    ```php
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        // 无 / 的单 IP 直接比较
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        list($subnet, $mask) = explode('/', $cidr, 2);
        $mask = (int)$mask;

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($mask < 0 || $mask > 32) return false;
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask));
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($mask < 0 || $mask > 128) return false;
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            $maskFull = (int)($mask / 8);
            $maskPartial = $mask % 8;
            // 比较完整字节
            if ($maskFull > 0 && substr($ipBin, 0, $maskFull) !== substr($subnetBin, 0, $maskFull)) {
                return false;
            }
            // 比较部分字节
            if ($maskPartial > 0) {
                $ipByte = ord($ipBin[$maskFull]);
                $subnetByte = ord($subnetBin[$maskFull]);
                $maskBits = (0xff << (8 - $maskPartial)) & 0xff;
                if (($ipByte & $maskBits) !== ($subnetByte & $maskBits)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
    ```
- **Verification**: 
  - IPv4 单 IP / CIDR 匹配
  - IPv6 单 IP / CIDR 匹配
  - IPv4 与 IPv6 混合不匹配

---

## [ ] Task 5: APK 注入去重 Redis 分布锁 (FR-1.5 / C6)
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/ApkInjectService.php:42-81](file:///workspace/server/app/service/ApkInjectService.php)
  - `createTask` 用 Redis SET NX EX 加锁
  - 修改要点：
    ```php
    // 3. SHA-256 去重 (Redis 分布锁防并发绕过)
    $dedupLockKey = 'apk_dedup:' . $sha256 . ':' . $merchantId;
    $redis = Cache::store('redis')->handler();
    $lockToken = bin2hex(random_bytes(8));
    $lockAcquired = $redis->set($dedupLockKey, $lockToken, ['NX', 'EX' => 5]);
    if (!$lockAcquired) {
        throw new \RuntimeException('该APK正在处理中，请稍后重试');
    }

    try {
        $existing = ApkInjectTask::where('file_sha256', $sha256)
            ->where('merchant_id', $merchantId)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW))
            ->whereIn('status', [ApkInjectTask::STATUS_PENDING, ApkInjectTask::STATUS_PROCESSING, ApkInjectTask::STATUS_COMPLETED])
            ->find();
        if ($existing) {
            throw new \RuntimeException('该APK在24小时内已提交过注入任务');
        }

        // ... 原有任务创建逻辑
    } finally {
        // Lua 脚本释放锁 (仅释放自己的锁)
        $lua = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
        $redis->eval($lua, [$dedupLockKey, $lockToken], 1);
    }
    ```
- **Verification**: 
  - 并发请求同 sha256 只一个成功

---

## [ ] Task 6: 新增 unbind_time 字段 migration
- **Priority**: critical
- **Depends On**: None
- **Description**:
  - 创建 `server/database/migrations/20240727000001_add_unbind_time_to_devices.php`
  - `ca_devices` 表新增 `unbind_time` 字段
  - 实现要点：
    ```php
    <?php
    use think\migration\Migrator;

    class AddUnbindTimeToDevices extends Migrator
    {
        public function up()
        {
            $table = $this->table('ca_devices');
            if (!$table->hasColumn('unbind_time')) {
                $table->addColumn('unbind_time', 'datetime', [
                    'null' => true,
                    'default' => null,
                    'after' => 'bind_time',
                    'comment' => '解绑时间',
                ])->update();
            }
        }

        public function down()
        {
            $table = $this->table('ca_devices');
            if ($table->hasColumn('unbind_time')) {
                $table->removeColumn('unbind_time')->update();
            }
        }
    }
    ```
- **Verification**: 
  - `php think migrate:run` 后 `ca_devices` 有 `unbind_time` 字段
  - 现有 `voidCard` 第 600 行的 `unbind_time` 不再报错

---

## [ ] Task 7: processRecharge 钱包加行锁 (FR-2.1 / I1)
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - 与 Task 1 一并修改 `processRecharge`
  - `Wallet::where(...)->lock(true)->find()`
  - 复用 `handleNotify` 的事务（processRecharge 在事务内被调用）
- **Verification**: 
  - 代码审查 `processRecharge` 含 `lock(true)`

---

## [ ] Task 8: 退款校验余额+同步 wallet (FR-2.2 / I2)
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - 修改 [server/app/service/PaymentService.php:352-358](file:///workspace/server/app/service/PaymentService.php)
  - 退款前校验 `merchant.balance >= amount`
  - 同步扣减 `wallet.balance`（保持双字段一致）
  - 修改要点：
    ```php
    // refundOrder 中 TYPE_RECHARGE 分支
    $merchant = Merchant::where('id', $order->merchant_id)->lock(true)->find();
    if (!$merchant) {
        throw new \RuntimeException('商户不存在');
    }
    if (bccomp(strval($merchant->balance), strval($order->amount), 2) < 0) {
        throw new \RuntimeException('商户余额不足，无法退款');
    }
    $merchant->balance = bcsub(strval($merchant->balance), strval($order->amount), 2);
    $merchant->save();

    // 同步扣减 wallet.balance (保持一致)
    $wallet = Wallet::where('user_id', $merchant->user_id)
        ->where('type', 1)->lock(true)->find();
    if ($wallet) {
        $wallet->balance = bcsub(strval($wallet->balance), strval($order->amount), 2);
        $wallet->save();

        // 记录退款流水
        $transaction = new WalletTransaction();
        $transaction->wallet_id = $wallet->id;
        $transaction->type = 2; // 退款
        $transaction->amount = $order->amount;
        $transaction->related_order = $order->order_no;
        $transaction->balance_after = $wallet->balance;
        $transaction->remark = '充值退款';
        $transaction->settle_status = 1;
        $transaction->save();
    }
    ```
- **Verification**: 
  - 余额不足时抛异常
  - 退款后 `merchant.balance == wallet.balance`

---

## [ ] Task 9: 缓存模型 exists=true (FR-2.3 / I3)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/CardService.php:140-163](file:///workspace/server/app/service/CardService.php)
  - 命中缓存时用 `newInstance($data, true)` 确保 `exists=true`
  - 修改要点：
    ```php
    public static function getCardByHash(string $cardNoHash, int $appId): ?Card
    {
        $cacheKey = 'card:' . $cardNoHash . ':' . $appId;
        $card = Cache::get($cacheKey);
        if ($card) {
            // newInstance($data, true) 让 exists=true, save() 走 UPDATE 而非 INSERT
            return (new Card())->newInstance($card, true);
        }

        $card = Card::where('card_no_hash', $cardNoHash)
            ->where('app_id', $appId)
            ->find();
        if ($card) {
            Cache::set($cacheKey, $card->toArray(), self::CACHE_TTL);
        }
        return $card;
    }
    ```
- **Verification**: 
  - 命中缓存的对象 `exists=true`
  - `save()` 执行 UPDATE

---

## [ ] Task 10: 封禁后强制清缓存+读路径校验 (FR-2.4 / I4)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/CardService.php:504-537](file:///workspace/server/app/service/CardService.php) banCard
  - 修改 [server/app/service/CardService.php:575-608](file:///workspace/server/app/service/CardService.php) voidCard
  - `clearCardCache` 后再读一次确认
  - `verifyCard` 读缓存后对 `status` 做轻量 DB 校验
  - 修改要点：
    ```php
    // verifyCard 入口处
    public static function verifyCard(string $cardNo, int $appId): array
    {
        $cardNoHash = self::hashCardNo($cardNo);
        $card = self::getCardByHash($cardNoHash, $appId);
        if (!$card) { /* ... */ }

        // 轻量 DB 校验: 仅查 status, 防止缓存与 DB 不一致 (如封禁后清缓存失败)
        $dbStatus = Card::where('id', $card->id)->value('status');
        if ($dbStatus !== null && $dbStatus != $card->status) {
            // 缓存状态与 DB 不一致, 清缓存并使用 DB 状态
            self::clearCardCache($cardNoHash);
            $card->status = $dbStatus;
        }
        // ... 原有逻辑
    }
    ```
- **Verification**: 
  - 封禁后即使缓存未清，verifyCard 也能拒绝

---

## [ ] Task 11: JWT 黑名单验签后查询 (FR-2.5 / I5)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/JwtService.php:135-158](file:///workspace/server/app/service/JwtService.php)
  - `isBlacklisted` 改为先 `JWT::decode` 验签
  - 修改要点：
    ```php
    public function isBlacklisted(string $token): bool
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, 'HS256'));
            $jti = $payload->jti ?? null;
            if (!$jti) {
                return false;
            }
            return (bool) Cache::store('redis')->handler()->get(
                $this->blacklistPrefix . $jti
            );
        } catch (\Exception $e) {
            // 验签失败或格式错误, 视为已失效 (拒绝)
            return true;
        }
    }
    ```
- **Verification**: 
  - 伪造 jti 的 token 验签失败返回 true

---

## [ ] Task 12: 彩虹支付签名字段白名单 (FR-2.6 / I6)
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 修改 [server/app/library/payment/drivers/CaihongPay.php:100-135](file:///workspace/server/app/library/payment/drivers/CaihongPay.php)
  - `verifyNotify` 用白名单字段参与签名
  - 修改要点：
    ```php
    protected function generateSign(array $data, string $key): string
    {
        // 彩虹支付官方文档签名白名单字段
        $signFields = ['pid', 'trade_no', 'out_trade_no', 'type', 'name', 'money', 'trade_status'];
        $signData = [];
        foreach ($signFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                $signData[$field] = $data[$field];
            }
        }
        ksort($signData);
        $signString = urldecode(http_build_query($signData)) . $key;
        return strtolower(md5($signString));
    }
    ```
- **Verification**: 
  - 含额外字段的回调能通过签名验证

---

## [ ] Task 13: 支付配置校验+密钥加密 (FR-2.7 / I7)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [server/app/controller/admin/PaymentController.php:20-37](file:///workspace/server/app/controller/admin/PaymentController.php)
  - `api_url` 校验 URL + https
  - `key` 用 `AesEncrypt::encrypt` 加密落库
  - `pid` 校验整数
  - 修改要点：
    ```php
    public function updateConfig(Request $request)
    {
        $apiUrl = $request->post('api_url', '');
        $pid = $request->post('pid', '');
        $key = $request->post('key', '');

        // 校验 api_url: 必须是 https URL
        if (!empty($apiUrl)) {
            if (!filter_var($apiUrl, FILTER_VALIDATE_URL) || strpos($apiUrl, 'https://') !== 0) {
                return error('api_url 必须是有效的 https URL');
            }
        }

        // 校验 pid: 整数
        if (!empty($pid) && !ctype_digit(strval($pid))) {
            return error('pid 必须是整数');
        }

        // ... 读取现有配置
        $config = SystemConfig::where('code', 'payment_caihong')->find();
        $data = $config ? json_decode($config->value, true) : [];

        if (!empty($apiUrl)) $data['api_url'] = $apiUrl;
        if (!empty($pid)) $data['pid'] = $pid;
        if (!empty($key)) $data['key'] = AesEncrypt::encrypt($key); // 加密落库

        // ... 保存
    }
    ```
  - 读取支付配置时解密：
    ```php
    $config = SystemConfig::where('code', 'payment_caihong')->find();
    $data = json_decode($config->value, true);
    if (!empty($data['key'])) {
        $data['key'] = AesEncrypt::decrypt($data['key']);
    }
    ```
- **Verification**: 
  - http URL 被拒绝
  - `key` 落库为密文

---

## [ ] Task 14: banCard/voidCard 事务 (FR-2.8 / I9)
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - 修改 [server/app/service/CardService.php:504-537](file:///workspace/server/app/service/CardService.php) banCard
  - 修改 [server/app/service/CardService.php:575-608](file:///workspace/server/app/service/CardService.php) voidCard
  - `Db::startTrans()` 包裹
  - 修改要点：
    ```php
    public static function banCard(int $cardId, string $reason = ''): array
    {
        Db::startTrans();
        try {
            $card = Card::where('id', $cardId)->lock(true)->find();
            if (!$card) {
                Db::rollback();
                return ['success' => false, 'message' => '卡密不存在'];
            }
            if ($card->status == Card::STATUS_BANNED) {
                Db::rollback();
                return ['success' => false, 'message' => '卡密已封禁'];
            }

            $card->status = Card::STATUS_BANNED;
            $card->save();

            Device::where('card_id', $cardId)->update([
                'is_online' => 0,
            ]);

            Db::commit();
            self::clearCardCache($card->card_no_hash);
            return ['success' => true, 'message' => '封禁成功'];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
    ```
- **Verification**: 
  - 设备更新失败时回滚卡密状态

---

## [ ] Task 15: Minor 修复 (M5/M6)
- **Priority**: low
- **Depends On**: None
- **Description**:
  - **M5**: [server/app/service/ApkInjectService.php:53](file:///workspace/server/app/service/ApkInjectService.php) `mt_rand` → `random_int`
  - **M6**: [server/app/middleware/ApiAuthMiddleware.php:124-127](file:///workspace/server/app/middleware/ApiAuthMiddleware.php) 读取 `php://input` 前校验 Content-Length
  - 修改要点：
    ```php
    // ApkInjectService.php
    $taskNo = 'APK' . date('YmdHis') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    // ApiAuthMiddleware.php
    $contentLength = intval($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 10 * 1024 * 1024) { // 10MB
        return $this->unauthorized('Request body too large');
    }
    $body = file_get_contents('php://input');
    ```
- **Verification**: 
  - `grep "mt_rand" server/app/service/ApkInjectService.php` 无输出

---

## [ ] Task 16: 语法验证 + 提交推送
- **Priority**: high
- **Depends On**: All
- **Description**:
  - `php -l` 所有修改的 PHP 文件
  - `bash -n` 修改的 shell 文件（如有）
  - git add 所有改动并提交
  - 推送到 origin main
- **Verification**: 
  - 所有语法检查通过
  - git push 成功

---

## 执行顺序建议
1. **批次 A (Critical)**: Task 6 (migration) → Task 1, 2, 3, 4, 5 (并行)
2. **批次 B (Important)**: Task 7, 8, 9, 10, 11, 12, 13, 14 (并行)
3. **批次 C (Minor)**: Task 15
4. **批次 D (验证)**: Task 16
