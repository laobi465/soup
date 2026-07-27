# 全面审查一键修复 - 验证清单

## Task 1: 充值统一走 wallet 账本 (FR-1.1 / C1)
- [ ] CP-1.1: handleNotify 移除对 merchant.balance 的直接修改
- [ ] CP-1.2: processRecharge 同步更新 merchant.balance = wallet.balance
- [ ] CP-1.3: 充值后 merchant.balance == wallet.balance
- [ ] CP-1.4: wallet_transactions 表仅 1 条充值流水

## Task 2: rebindDevice 事务+行锁+审计 (FR-1.2 / C2)
- [ ] CP-2.1: 整体包裹 Db::startTrans()
- [ ] CP-2.2: Card 查询加 lock(true)
- [ ] CP-2.3: 旧设备查询加 lock(true)
- [ ] CP-2.4: 旧设备软删 (is_online=0, unbind_time)
- [ ] CP-2.5: 新建设备记录 (保留审计链)
- [ ] CP-2.6: 新设备写入 bind_time
- [ ] CP-2.7: 异常时 Db::rollback()

## Task 3: Hash 路径 IP 黑名单 (FR-1.3 / C4)
- [ ] CP-3.1: recordBruteForceFailByHash 检查 IP 失败次数达阈值
- [ ] CP-3.2: 阈值后调用 banIp 加入 ca_risk_blacklist
- [ ] CP-3.3: banIp 复用现有表 (type=1)
- [ ] CP-3.4: 黑名单 expire_time 为 24h

## Task 4: ipInCidr 支持 IPv6 (FR-1.4 / C5)
- [ ] CP-4.1: 无 / 的单 IP 直接比较
- [ ] CP-4.2: IPv4 用 ip2long + mask 0-32 校验
- [ ] CP-4.3: IPv6 用 inet_pton 按字节比较
- [ ] CP-4.4: IPv6 mask 0-128 校验
- [ ] CP-4.5: IPv4 与 IPv6 混合不匹配
- [ ] CP-4.6: filter_var 校验 IP 格式

## Task 5: APK 注入去重 Redis 分布锁 (FR-1.5 / C6)
- [ ] CP-5.1: createTask 用 Redis SET NX EX 加锁
- [ ] CP-5.2: 锁 key 为 apk_dedup:{sha256}:{merchant_id}
- [ ] CP-5.3: 锁 TTL 5s
- [ ] CP-5.4: 锁获取失败抛异常
- [ ] CP-5.5: Lua 脚本释放锁 (仅释放自己的)
- [ ] CP-5.6: try-finally 确保锁释放

## Task 6: unbind_time migration
- [ ] CP-6.1: 创建 migration 文件
- [ ] CP-6.2: ca_devices 新增 unbind_time 字段
- [ ] CP-6.3: 字段 nullable, default null
- [ ] CP-6.4: down 方法可回滚
- [ ] CP-6.5: php -l 语法通过

## Task 7: processRecharge 钱包加行锁 (FR-2.1 / I1)
- [ ] CP-7.1: Wallet::where(...)->lock(true)->find()
- [ ] CP-7.2: 在 handleNotify 事务内调用

## Task 8: 退款校验余额+同步 wallet (FR-2.2 / I2)
- [ ] CP-8.1: 退款前 bccomp 校验 merchant.balance >= amount
- [ ] CP-8.2: 余额不足抛异常
- [ ] CP-8.3: 同步扣减 wallet.balance
- [ ] CP-8.4: 记录退款流水 (type=2)
- [ ] CP-8.5: 退款后 merchant.balance == wallet.balance

## Task 9: 缓存模型 exists=true (FR-2.3 / I3)
- [ ] CP-9.1: 命中缓存时用 newInstance($data, true)
- [ ] CP-9.2: exists 属性为 true
- [ ] CP-9.3: save() 执行 UPDATE 而非 INSERT

## Task 10: 封禁后强制清缓存+读路径校验 (FR-2.4 / I4)
- [ ] CP-10.1: verifyCard 读缓存后查询 DB status
- [ ] CP-10.2: 状态不一致时清缓存并用 DB 状态
- [ ] CP-10.3: 仅查 status 字段 (轻量校验)

## Task 11: JWT 黑名单验签后查询 (FR-2.5 / I5)
- [ ] CP-11.1: isBlacklisted 先 JWT::decode 验签
- [ ] CP-11.2: 验签失败返回 true (视为已失效)
- [ ] CP-11.3: 验签成功用 payload['jti'] 查询黑名单

## Task 12: 彩虹支付签名字段白名单 (FR-2.6 / I6)
- [ ] CP-12.1: generateSign 用白名单字段
- [ ] CP-12.2: 白名单含 pid/trade_no/out_trade_no/type/name/money/trade_status
- [ ] CP-12.3: 额外字段不参与签名

## Task 13: 支付配置校验+密钥加密 (FR-2.7 / I7)
- [ ] CP-13.1: api_url 用 filter_var 校验
- [ ] CP-13.2: api_url 强制 https
- [ ] CP-13.3: pid 校验整数
- [ ] CP-13.4: key 用 AesEncrypt::encrypt 加密落库
- [ ] CP-13.5: 读取时 AesEncrypt::decrypt 解密

## Task 14: banCard/voidCard 事务 (FR-2.8 / I9)
- [ ] CP-14.1: banCard 包裹 Db::startTrans()
- [ ] CP-14.2: voidCard 包裹 Db::startTrans()
- [ ] CP-14.3: 异常时 Db::rollback()
- [ ] CP-14.4: Card 查询加 lock(true)

## Task 15: Minor 修复 (M5/M6)
- [ ] CP-15.1: ApkInjectService mt_rand → random_int
- [ ] CP-15.2: ApiAuthMiddleware 读取 php://input 前校验 Content-Length
- [ ] CP-15.3: Content-Length > 10MB 返回 413

## Task 16: 语法验证 + 提交推送
- [ ] CP-16.1: 所有修改的 PHP 文件 php -l 通过
- [ ] CP-16.2: git commit message 详细列出修复项
- [ ] CP-16.3: git push origin main 成功

---

## 端到端验证（可选，需真实环境）
- [ ] E2E-1: 充值后 merchant.balance == wallet.balance
- [ ] E2E-2: 并发换绑只一个成功
- [ ] E2E-3: IPv6 客户端白名单匹配正确
- [ ] E2E-4: 并发 APK 去重只一个成功
- [ ] E2E-5: 退款余额不足时抛异常
