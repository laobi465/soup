# 全面审查一键修复 - PRD

## Overview
- **Summary**: 修复 `/review` 全面审查确认的 5 Critical + 8 Important 问题，聚焦资金安全、并发竞态、IPv6 兼容、APK 注入去重、缓存一致性。
- **Purpose**: 消除生产环境资金对账偏差、暴力破解 IP 黑名单缺失、并发换绑绕过、IPv6 白名单误判、APK 注入去重竞态等高风险缺陷。
- **Target Users**: 平台所有用户（商户/代理/终端用户）+ 运维

## Background & Context
- `/review 项目全部文件` 审查发现 27 个问题，经核查后确认：
  - **C3 误报撤销**：控制器层 `CardApiController` 已正确实现暴力破解防护（`recordBruteForceFailByHash` + `checkBruteForce`），`verifyCard` 内部不重复检查是合理设计。
  - **C4 降级**：Hash 路径在卡密不存在场景下无法 banCard（无 cardId），但应在 IP 失败次数达阈值后加入 IP 黑名单。
- 其余 Critical/Important 经代码核查确认存在，需修复。

## Goals
- 修复 5 个确认 Critical 问题（C1、C2、C4、C5、C6）
- 修复 8 个 Important 问题（I1、I2、I3、I4、I5、I6、I7、I9）
- 保持现有 API 接口与数据库结构兼容
- 不引入新功能，仅修复缺陷

## Non-Goals (Out of Scope)
- 重构支付系统架构（保持现有 wallet + merchant 双字段，但统一更新逻辑）
- 引入状态机库（S6 建议）
- 引入幂等键机制（S2 建议）
- 切换支付签名算法（M4 MD5 → HMAC-SHA256，需网关支持）
- 优化 N+1 查询（M2，性能优化非紧急）
- C3 已撤销，不修改 verifyCard

## Functional Requirements

### FR-1: Critical 修复

#### FR-1.1: 充值订单统一走 wallet 账本 (C1)
- `PaymentService::handleNotify` 第 227-230 行移除对 `merchant.balance` 的直接修改
- 充值统一由 `processRecharge` 更新 `wallet.balance`
- `processRecharge` 内同步更新 `merchant.balance`（保持双字段一致，用同一事务+lock）
- 退款 `refundOrder` 也同步更新 `wallet.balance`（保持双字段一致）

#### FR-1.2: rebindDevice 事务+行锁+审计 (C2)
- 整体包裹 `Db::startTrans()`
- 查询 Card 时加 `lock(true)`
- 查询旧设备时加 `lock(true)`
- 旧设备改为软删（`is_online=0`, `unbind_time` 字段）+ 新建设备记录（保留审计链）
- 新建设备记录写入 `bind_time`

#### FR-1.3: Hash 路径 IP 黑名单 (C4)
- `recordBruteForceFailByHash` 在 IP 失败次数达阈值后，将 IP 加入 `ca_risk_blacklist` 表
- 阈值复用 `BRUTE_FORCE_IP_LIMIT`（如 100 次）
- `ApiAuthMiddleware` 入口检查 IP 是否在黑名单

#### FR-1.4: ipInCidr 支持 IPv6 (C5)
- 用 `filter_var($ip, FILTER_VALIDATE_IP)` 校验
- IPv4 用 `ip2long`，IPv6 用 `inet_pton` 按字节比较
- 校验 mask 范围（IPv4: 0-32, IPv6: 0-128）
- `explode('/', $cidr)` 失败时返回 false（无 `/` 的单 IP）

#### FR-1.5: APK 注入去重 Redis 分布锁 (C6)
- `createTask` 用 Redis SET NX EX 加锁 `apk_dedup:{sha256}:{merchant_id}` (TTL 5s)
- 获取锁后才查询+插入，插入完成释放锁
- 锁获取失败直接拒绝（防并发绕过去重）

### FR-2: Important 修复

#### FR-2.1: processRecharge 钱包加行锁 (I1)
- `Wallet::where(...)->lock(true)->find()`
- 整个流程在同一事务内（复用 handleNotify 的事务）

#### FR-2.2: 退款校验余额 (I2)
- 退款前 `bccomp($merchant->balance, $order->amount, 2) < 0` 则抛异常
- 退款同步更新 `wallet.balance`（与 FR-1.1 保持双字段一致）

#### FR-2.3: 缓存命中时模型 exists=true (I3)
- `getCardByHash` 命中缓存时用 `(new Card())->newInstance($data, true)`
- 确保 `exists=true`，避免 save() 触发 INSERT

#### FR-2.4: 封禁后强制清缓存+读路径校验 (I4)
- `banCard`/`voidCard` 调用 `clearCardCache` 后再读一次确认清除
- `verifyCard` 读缓存后对 `status` 字段做一次轻量 DB 校验（仅查 status，不查全行）

#### FR-2.5: JWT 黑名单验签后查询 (I5)
- `isBlacklisted` 改为先 `JWT::decode` 验签，再用 payload['jti'] 查询
- 验签失败直接返回 true（视为已失效）

#### FR-2.6: 彩虹支付签名字段白名单 (I6)
- `verifyNotify` 用白名单字段参与签名（pid/trade_no/out_trade_no/type/name/money/trade_status）
- 其余字段一律剔除

#### FR-2.7: 支付配置校验+密钥加密 (I7)
- `api_url` 用 `filter_var(FILTER_VALIDATE_URL)` 校验且强制 https
- `key` 用 `AesEncrypt::encrypt` 加密落库，读取时解密
- `pid` 校验为整数

#### FR-2.8: banCard/voidCard 事务 (I9)
- `Db::startTrans()` 包裹 card.save + device.update
- 失败回滚

### FR-3: Minor 修复（顺带）
- `mt_rand` → `random_int` (M5)
- `php://input` 读取前校验 Content-Length (M6)

## Non-Functional Requirements
- **向后兼容**: 不改变 API 接口、数据库表结构（仅加索引/字段如需）
- **事务一致性**: 所有资金操作在事务内+行锁
- **幂等性**: 修复不破坏现有幂等设计
- **测试**: 关键修复需有可验证方法

## Constraints
- 不引入新依赖库
- 不改变 `merchant.balance` 字段存在性（向后兼容，仅改写入逻辑）
- 数据库迁移用 Phinx migration（新增 `unbind_time` 字段需 migration）
- IPv6 支持不能破坏现有 IPv4 行为

## Acceptance Criteria

### AC-1: 充值不再双重复记
- **Given**: 商户充值 100 元
- **When**: 支付回调成功
- **Then**: `merchant.balance` 和 `wallet.balance` 各 +100（保持一致）
- **And**: `wallet_transactions` 表有 1 条充值流水（非 2 条）
- **Verification**: 代码审查 `handleNotify` 不再直接修改 `merchant.balance`

### AC-2: 换绑并发安全
- **Given**: 同一卡密两个并发换绑请求
- **When**: 同时执行 rebindDevice
- **Then**: 只有一个请求成功，另一个等待或失败
- **And**: `ca_devices` 表有旧设备软删记录 + 新设备记录
- **Verification**: 代码审查有 `Db::startTrans` + `lock(true)`

### AC-3: IPv6 白名单正确
- **Given**: IPv6 客户端 `2001:db8::1`
- **When**: 配置白名单 `2001:db8::/64`
- **Then**: `ipInCidr` 返回 true
- **Given**: IPv6 客户端 `2001:db9::1`
- **Then**: `ipInCidr` 返回 false
- **Verification**: 单元测试覆盖 IPv4/IPv6 单 IP/CIDR

### AC-4: APK 去重并发安全
- **Given**: 两个并发请求同 sha256
- **When**: 同时调 createTask
- **Then**: 只有一个成功，另一个抛"该APK在24小时内已提交过注入任务"
- **Verification**: Redis 锁日志

### AC-5: 退款不产生负数
- **Given**: 商户余额 50 元，订单金额 100 元
- **When**: 退款
- **Then**: 抛异常"商户余额不足，无法退款"
- **And**: 余额不变
- **Verification**: 单元测试

### AC-6: 缓存模型可正确 save
- **Given**: 卡密缓存命中
- **When**: 对返回对象调 save()
- **Then**: 执行 UPDATE 而非 INSERT
- **Verification**: `exists` 属性为 true

### AC-7: JWT 黑名单验签
- **Given**: 伪造 jti 的 token
- **When**: 调 isBlacklisted
- **Then**: 验签失败，返回 true（视为已失效）
- **Verification**: 单元测试

### AC-8: 彩虹支付签名白名单
- **Given**: 网关回调含额外字段 `order_uid`
- **When**: verifyNotify
- **Then**: 签名验证用白名单字段，额外字段不参与
- **And**: 合法回调通过验证
- **Verification**: 单元测试

## Open Questions
- [ ] `merchant.balance` 与 `wallet.balance` 是否真的是冗余双字段？（FR-1.1 假设是，需确认）
- [ ] rebindDevice 软删旧设备需新增 `unbind_time` 字段，是否需要 migration？（推荐需要）
- [ ] IP 黑名单表 `ca_risk_blacklist` 是否已存在？（需核查）
- [ ] `clearBruteForceByHash` 是否需要补充？（S4 建议，可顺带做）
