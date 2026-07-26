# 代码审查问题修复 - Product Requirement Document

## Overview
- **Summary**: 修复代码审查中发现的所有严重问题和重要问题，确保系统达到生产级安全与业务正确性标准
- **Purpose**: 解决三路代码审查（安全鉴权、核心业务逻辑、架构与前端）发现的 22 个严重问题和 20+ 个重要问题，消除安全漏洞、业务逻辑错误、并发缺陷
- **Target Users**: 开发团队、运维团队、最终商户和终端用户

## Goals
- 修复所有 Critical 级安全漏洞，消除认证绕过、数据泄露、越权访问风险
- 修复所有 Critical 级业务逻辑缺陷，确保发卡、支付、佣金等资金链路正确
- 修复架构与部署阻断性问题，确保生产环境可正常运行
- 修复 High 优先级 Important 问题，提升系统健壮性
- 保持代码风格与现有架构一致

## Non-Goals (Out of Scope)
- 不新增功能模块
- 不重构整体架构
- 不做 Minor 级问题的优化（留待后续迭代）
- 不补充完整测试用例（仅修复功能正确性）
- 不升级依赖版本
- 不做 UI/UX 改进

## Background & Context
网络验证卡密 SaaS 平台已完成全部 21 个功能模块的开发，但三路并行代码审查发现了大量严重问题：
1. 安全层面：密钥硬编码、权限中间件未接线、卡密明文进日志、防爆破逻辑倒置
2. 业务层面：发卡读取不存在字段导致业务断裂、支付/余额无并发锁、限购字段缺失
3. 架构层面：Nginx 配置错误导致生产不可用、菜单权限配置不一致

这些问题若直接上线会造成：认证绕过、数据泄露、资金损失、业务不可用。必须在上线前全部修复。

## Functional Requirements

### FR-1: 安全密钥与默认值修复
- 移除 JWT 密钥硬编码默认值，启动时校验密钥必填
- 移除 AES 加密密钥硬编码默认值，启动时校验
- 删除 AppSecret 解密失败回退到 bcrypt hash 的逻辑
- 将 `.example.env` 默认 `APP_DEBUG` 改为 `false`
- 修复 Docker Compose 硬编码数据库密码

### FR-2: 权限系统接线修复
- 商户路由组绑定 PermissionMiddleware
- admin 路由的 PermissionMiddleware 传入正确的权限参数
- DataPermissionMiddleware 注册到 merchant 路由组
- 所有 merchant 控制器查询时按 `app_ids` 过滤（子账号数据隔离）
- 子角色权限从数据库 `sub_roles.permissions` 读取而非硬编码

### FR-3: 卡密安全与日志修复
- API 日志中卡密号脱敏（保留前4后4，中间用*替换）
- 设备指纹哈希后记录
- 防爆破逻辑重写：无论卡密是否存在都按 IP+card_hash 计数
- verify 接口对未激活卡不计爆破（正确卡号不算爆破）
- Nonce 防重放改用 Redis SETNX 原子操作
- 限流计数改用 Redis INCR 原子操作，且在业务执行前计数

### FR-4: 发卡业务修复
- 新增 `card_no_encrypted` 字段（AES 加密明文），生成时同时写入
- 发卡服务从加密字段读取明文
- 限购服务新增 `buyer_ip` 和 `device_id` 字段到 orders 表
- 下单时写入 buyer_ip 和 device_id
- 修复 quantity 多份只发 1 张的问题：循环发卡 N 张
- quantity 增加上限限制（单次最多 100 份）
- ShopController 校验商户状态
- agent_id 从服务端解析（签名 cookie/邀请码），禁止前端直传

### FR-5: 支付与资金并发修复
- 余额支付加行锁（SELECT ... FOR UPDATE）
- 支付回调读取订单加行锁
- processOrderPaid 移入事务内
- 商品库存扣减改用原子 DEC 操作
- 佣金 D+1 结算用条件更新确保幂等
- 退款扣回佣金校验余额，不足则挂账
- 订单号生成改用安全随机数（random_int）

### FR-6: 架构与部署修复
- 修复 Nginx 生产配置 `/api/` 代理错误（删除 proxy_pass，保留 fastcgi_pass）
- Nginx 增加安全响应头（HSTS、X-Frame-Options、X-Content-Type-Options）
- 全局中间件增加安全头中间件
- 注册接口增加 LoginThrottleMiddleware 限流
- AdminMenuSeeder 与 permission.php 权限配置对齐
- 卡密导入弹窗重写（改用 el-dialog + el-form，移除 dangerouslyUseHTMLString）

### FR-7: 其他高优先级修复
- CaihongPay 开启 SSL 证书验证
- 登录风控增加 IP 维度，username 统一小写
- 卡密激活/续费/绑定加行锁
- 前端 Token 存储改 sessionStorage
- 卡密列表统计改用 GROUP BY 单条查询
- 卡密导出 CSV 加 UTF-8 BOM

## Non-Functional Requirements

- **NFR-1**: 所有修复不破坏现有功能，向后兼容
- **NFR-2**: 修复后 PHP 语法检查通过，前端 build 成功
- **NFR-3**: 安全修复不引入性能回归（验证接口 P99 ≤ 100ms）
- **NFR-4**: 数据库迁移可逆（down 方法完整）
- **NFR-5**: 代码风格与现有项目一致

## Constraints

- **Technical**: ThinkPHP 8 / PHP 8.2 / Vue 3 / Element Plus，不升级框架
- **Business**: 不改变业务流程和用户体验，仅修复正确性问题
- **Dependencies**: 不新增第三方依赖，复用现有库
- **Data**: 数据迁移需考虑现有数据，不丢数据

## Assumptions

- Redis 可用，可使用 SETNX/INCR 等原子操作
- 现有数据库中的卡密数据需要回填 `card_no_encrypted`（但因明文已丢失，只能对新生成的卡密生效，历史卡密发卡功能不可用）
- 权限配置以 permission.php 为准，AdminMenuSeeder 向其对齐

## Acceptance Criteria

### AC-1: 密钥安全
- **Given**: 系统启动时未配置 JWT_SECRET 和 APP_SECRET_KEY
- **When**: 访问任意接口
- **Then**: 系统报错拒绝启动，不使用默认值
- **Verification**: `programmatic`

### AC-2: 权限中间件生效
- **Given**: 商户子账号登录
- **When**: 访问未授权的应用的卡密列表
- **Then**: 返回 403 或数据被过滤为空
- **Verification**: `programmatic`

### AC-3: 卡密日志脱敏
- **Given**: 调用卡密验证接口
- **When**: 查看 api_logs 表记录
- **Then**: request_data 中 card_no 为脱敏格式（如 VIP-****1234）
- **Verification**: `programmatic`

### AC-4: 防爆破正确
- **Given**: 同一 IP 连续请求不存在的卡号
- **When**: 达到 15 次失败
- **Then**: 该 IP 被限流/封禁，后续请求被拒绝
- **Verification**: `programmatic`

### AC-5: 发卡功能正确
- **Given**: 商品有 10 张卡密库存
- **When**: 用户购买 3 份并支付成功
- **Then**: 收到 3 张卡密，库存减 3
- **Verification**: `programmatic`

### AC-6: 支付并发安全
- **Given**: 同一订单同时收到两个支付回调
- **When**: 两个回调并发处理
- **Then**: 只处理一次，不重复发卡、不重复加款
- **Verification**: `programmatic`

### AC-7: Nginx 生产配置正确
- **Given**: 生产环境启动
- **When**: 前端发起 /api/ 请求
- **Then**: 请求正确转发到 PHP-FPM，返回正常响应
- **Verification**: `programmatic`

### AC-8: Nonce 原子性
- **Given**: 相同 nonce 的两个请求并发到达
- **When**: 同时通过 Nonce 检查
- **Then**: 只有一个通过，另一个返回 Nonce 重复错误
- **Verification**: `programmatic`

### AC-9: 限流原子性
- **Given**: 100 个并发请求同时到达
- **When**: 限流上限为 50
- **Then**: 恰好 50 个通过，50 个被限流
- **Verification**: `programmatic`

### AC-10: 代码可运行
- **Given**: 全部修复完成
- **When**: 执行 PHP 语法检查和前端 build
- **Then**: 全部通过，无错误
- **Verification**: `programmatic`

## Open Questions

- [ ] 历史卡密（无 card_no_encrypted）的发卡如何处理？建议标记为不可售或由商户重新导入
- [ ] 子角色权限字段结构（JSON 格式还是关联表）？当前设计是 JSON
- [ ] 限购的 device_id 如何生成？前端 JS 指纹还是后端多维度计算？
