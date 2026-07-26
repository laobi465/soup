# 网络验证卡密 SaaS 平台

一个功能完整、安全可靠的网络验证（卡密）SaaS 平台，支持多商户、多应用、卡密生成与验证、发卡交易、三级分销等完整业务闭环。

> 对标 b6w.top，采用 ThinkPHP 8 + Vue 3 + MySQL 8 + Redis 7 技术栈，Docker 一键部署。

## 核心功能

### 用户与权限
- **6 类角色**：超管、运营、商户、商户子账号、代理（三级）、终端用户
- **JWT 认证** + RBAC 权限模型 + 菜单/按钮级权限控制
- **登录风控**：失败锁定、异地登录检测、登录日志

### 商户与套餐
- **四档套餐**：入门版 / 标准版 / 专业版 / 旗舰版，应用数/卡密量/子账号数分级
- **额度管理**：商户钱包、额度校验、套餐到期处理
- **子账号系统**：商户自定义角色、数据权限按应用分配

### 应用管理
- **AppKey/AppSecret**：32 位随机生成，Secret 哈希存储仅展示一次
- **IP 白名单**：支持 CIDR 格式
- **设备绑定上限**：可配置单卡密绑定设备数
- **应用启停**：停用后所有卡密验证立即失效

### 卡密系统（核心）
- **7 种卡类型**：日卡 / 周卡 / 月卡 / 季卡 / 年卡 / 永久卡 / 试用卡
- **灵活生成**：自定义长度（16-32 位）、前缀、字符集（去除混淆字符）
- **批量生成**：单次最多 1000 张，SHA-256 哈希存储，明文仅展示一次
- **状态管理**：封禁 / 解封 / 作废 / 续费 / 设备解绑
- **导入导出**：支持 CSV/TXT 导入，Excel 导出

### 卡密验证 API（核心）
- **五重鉴权**：AppKey 校验 → 时间戳校验（5 分钟）→ Nonce 防重放 → HMAC-SHA256 签名 → IP 白名单
- **五维限流**：卡密 / IP / 设备 / 应用 / 商户，Redis 滑动窗口
- **9 个 API 接口**：验证、激活、换绑、查询、心跳、在线人数、公告、用户注册、用户登录
- **设备绑定**：首次激活绑定，达上限拒绝
- **心跳保活**：超时 3 次自动踢下线
- **防爆破**：5 分钟失败 15 次自动封禁卡密 + IP
- **Redis 缓存**：卡密状态缓存，验证接口 P99 ≤ 50ms

### 支付与发卡
- **彩虹易支付**：驱动抽象层，支持多支付通道切换
- **订单管理**：创建、状态流转、10 分钟超时自动关闭、回调幂等处理
- **发卡平台**：店铺配置、商品管理、限购（用户/IP/设备）、自动发卡 + 邮件通知
- **余额充值** + **退款流程**（二次验证，原路退回）

### 三级分销
- **邀请注册**：商户 → 一级 → 二级 → 三级，三级硬限制（不可发展下级）
- **差价模式**：代理拿货价 = 原价 × 折扣
- **佣金模式**：订单成交后三级分佣，D+1 结算解冻
- **代理钱包**：可用/冻结余额、佣金明细、提现（3% 手续费，最低 1 元）

### 运营支撑
- **数据仪表盘**：超管 / 商户 / 代理三端仪表盘，趋势图表，Excel 导出
- **安全风控**：黑名单（IP/设备/手机/邮箱）、异常订单/API 监控、告警通知
- **操作日志**：全量写操作审计、登录日志、API 调用日志
- **消息通知**：站内信、未读计数、邮件通知、消息模板
- **工单系统**：提交 / 回复 / 状态流转 / 附件
- **公告系统**：分类管理、弹窗公告、生效时间控制
- **系统配置**：7 个分组（基础/API/邮件/支付/卡密/安全/存储），Redis 缓存

### 基础设施
- **文件存储**：本地 / 阿里云 OSS / 腾讯云 COS / 七牛云 / MinIO 五种驱动
- **Docker 部署**：Nginx + PHP-FPM + MySQL + Redis + 队列 Worker + 定时任务
- **数据备份**：每日自动备份脚本
- **5 种语言 SDK**：Python / C# / Java / 易语言 / VB.NET

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端框架 | ThinkPHP 8 / PHP 8.2+ |
| 数据库 | MySQL 8.0 |
| 缓存 | Redis 7 |
| 认证 | JWT (firebase/php-jwt) |
| 导出 | PhpSpreadsheet |
| 前端框架 | Vue 3 (Composition API) |
| 构建工具 | Vite |
| UI 组件 | Element Plus |
| 状态管理 | Pinia |
| HTTP 客户端 | Axios |
| 容器化 | Docker + Docker Compose |

## 项目结构

```
/workspace
├── server/                    # 后端服务 (ThinkPHP 8)
│   ├── app/
│   │   ├── controller/        # 控制器 (admin/merchant/agent/api/common/shop)
│   │   ├── model/             # 数据模型 (20+)
│   │   ├── service/           # 服务层 (15+)
│   │   ├── middleware/        # 中间件 (鉴权/限流/权限/风控等)
│   │   ├── library/           # 类库 (payment/storage/aes/bcrypt)
│   │   └── common.php         # 公共函数
│   ├── config/                # 配置文件
│   ├── database/migrations/   # 数据库迁移 (26 张表)
│   ├── database/seeds/        # 数据填充
│   ├── route/app.php          # 路由定义
│   └── composer.json
├── admin/                     # 前端管理后台 (Vue3)
│   ├── src/
│   │   ├── api/               # API 接口封装
│   │   ├── components/        # 全局组件 (DataTable/FormModal等)
│   │   ├── layouts/           # 布局组件 (Admin/Shop/Agent)
│   │   ├── views/             # 页面 (admin/merchant/agent/shop)
│   │   ├── router/            # 路由 + 守卫
│   │   ├── store/             # Pinia 状态管理
│   │   ├── styles/            # 全局样式
│   │   └── utils/             # 工具函数 (Axios 封装)
│   └── vite.config.js
├── sdk/                       # 5 种语言 SDK
│   ├── python/                # Python SDK
│   ├── csharp/                # C# SDK (.NET Standard 2.0)
│   ├── java/                  # Java SDK (Java 8+, Maven)
│   ├── easy/                  # 易语言 SDK
│   └── vbnet/                 # VB.NET SDK
├── docker/                    # Docker 配置
│   ├── nginx/                 # Nginx (含生产配置)
│   ├── php/                   # PHP-FPM (含生产配置)
│   ├── mysql/                 # MySQL 优化配置
│   └── redis/                 # Redis 配置
├── docs/                      # 项目文档
│   ├── PRD-网络验证卡密SaaS平台.md
│   └── 宝塔面板部署指南.md
├── scripts/                   # 脚本
│   └── backup.sh              # 数据库备份脚本
├── docker-compose.yml         # 开发环境
├── docker-compose.prod.yml    # 生产环境
└── README.md
```

## 快速开始

### 环境要求
- Docker 20.10+ & Docker Compose v2+
- Node.js 18+（前端开发）
- Composer 2.x（PHP 依赖）

### 1. 启动后端服务

```bash
cd /workspace

# 启动所有服务
docker compose up -d

# 安装 PHP 依赖（首次）
docker compose exec php-fpm composer install

# 执行数据库迁移
docker compose exec php-fpm php think migrate:run

# 执行数据填充
docker compose exec php-fpm php think seed:run
```

服务地址：
- 后端 API：http://localhost:8000
- MySQL：localhost:3306（root / root123456）
- Redis：localhost:6379

### 2. 启动前端

```bash
cd /workspace/admin

# 安装依赖
npm install

# 开发模式
npm run dev
```

前端地址：http://localhost:5173

### 3. 默认账号

| 角色 | 账号 | 密码 |
|------|------|------|
| 超级管理员 | admin | admin123456 |

## 验证 API 快速接入

### 请求格式

所有 API 请求需携带以下 Header：

```
X-AppKey:     应用的 AppKey
X-Timestamp:  当前时间戳（秒）
X-Nonce:      随机字符串（防重放）
X-Sign:       HMAC-SHA256 签名
Content-Type: application/json
```

### 签名算法

```
签名串 = HTTP方法 + 请求路径 + 时间戳 + Nonce + 请求体
签名值 = HMAC-SHA256(签名串, AppSecret)
```

### Python SDK 示例

```python
from cardauth_sdk import CardAuthClient

# 初始化
client = CardAuthClient(
    app_key="你的AppKey",
    app_secret="你的AppSecret",
    base_url="http://localhost:8000"
)

# 卡密验证
result = client.verify(
    card_no="VIP-ABCD1234EFGH5678",
    device_fingerprint="device-001",
    device_name="我的电脑"
)

print(result)
# {"code": 0, "message": "success", "data": {"status": "active", "expire_at": 1234567890}}
```

## 统一响应格式

```json
{
  "code": 0,
  "message": "success",
  "data": {},
  "timestamp": 1234567890
}
```

### 错误码

| 错误码 | 说明 |
|--------|------|
| 0 | 成功 |
| 4001 | 签名错误 / AppKey 无效 |
| 4002 | 时间戳过期 |
| 4003 | Nonce 重复 |
| 4004 | IP 不在白名单 |
| 4005 | 请求超限 |
| 4101 | 卡密不存在 |
| 4102 | 卡密未激活 |
| 4103 | 卡密已到期 |
| 4104 | 卡密已封禁 |
| 4105 | 卡密已作废 |
| 4106 | 设备绑定数达上限 |
| 4107 | 应用已停用 |
| 4108 | 商户已过期 |

## 生产部署

### Docker Compose 生产部署

```bash
# 使用生产配置启动
docker compose -f docker-compose.prod.yml up -d
```

生产配置包含：
- Nginx：HTTPS、Gzip 压缩、静态资源缓存、HTTP/2
- PHP-FPM：OPcache 优化、生产级配置
- MySQL：InnoDB 优化、慢查询日志、二进制日志
- Redis：持久化配置
- 队列 Worker 容器
- 定时任务容器

### 数据库备份

```bash
# 手动备份
bash scripts/backup.sh

# 添加定时任务（每日凌晨 3 点）
crontab -e
0 3 * * * /workspace/scripts/backup.sh
```

### 宝塔面板部署

详见 [宝塔面板部署指南](docs/宝塔面板部署指南.md)。

## 数据库表结构

共 26 张数据表（前缀 `ca_`）：

| 表名 | 说明 |
|------|------|
| ca_users | 用户表（超管/运营/商户/子账号/代理/终端用户） |
| ca_admin_menus | 菜单权限表 |
| ca_packages | 套餐表 |
| ca_merchants | 商户表 |
| ca_apps | 应用表 |
| ca_cards | 卡密表 |
| ca_card_batches | 卡密批次表 |
| ca_devices | 设备绑定表 |
| ca_api_logs | API 调用日志表 |
| ca_orders | 订单表 |
| ca_shop_products | 发卡商品表 |
| ca_agents | 代理表 |
| ca_wallets | 钱包表 |
| ca_wallet_transactions | 钱包流水表 |
| ca_withdraws | 提现记录表 |
| ca_operation_logs | 操作日志表 |
| ca_risk_blacklist | 风控黑名单表 |
| ca_announcements | 公告表 |
| ca_tickets | 工单表 |
| ca_ticket_replies | 工单回复表 |
| ca_system_configs | 系统配置表 |
| ca_messages | 站内消息表 |
| ca_sub_roles | 子账号角色表 |

## 开发规范

### 后端
- 遵循 PSR-12 编码规范
- 控制器薄，业务逻辑在 Service 层
- 统一响应格式 `{code, message, data}`
- 数据权限：商户只能操作自己的数据

### 前端
- Composition API + `<script setup>`
- 蓝色主色调，简约商务风，仅亮色主题
- 全局组件复用（DataTable / FormModal / ConfirmDialog 等）
- 响应式适配：PC / 平板 / 手机三档断点

## License

MIT
