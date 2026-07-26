# 网络验证卡密SaaS平台

一个功能完整的网络验证卡密 SaaS 平台，支持卡密生成、验证、管理等功能。

## 技术栈

### 后端
- **框架**: ThinkPHP 8
- **语言**: PHP 8.2+
- **数据库**: MySQL 8.0
- **缓存**: Redis 7
- **认证**: JWT (firebase/php-jwt)
- **Excel导出**: PhpSpreadsheet

### 前端
- **框架**: Vue 3
- **构建工具**: Vite
- **UI组件库**: Element Plus
- **状态管理**: Pinia
- **路由**: Vue Router 4
- **HTTP客户端**: Axios
- **代码规范**: ESLint + Prettier

### 基础设施
- **Web服务器**: Nginx
- **容器化**: Docker + Docker Compose

## 项目结构

```
/workspace
├── admin/                  # 前端管理后台 (Vue3)
│   ├── src/
│   │   ├── api/           # API 接口
│   │   ├── assets/        # 静态资源
│   │   ├── components/    # 公共组件
│   │   ├── layouts/       # 布局组件
│   │   ├── router/        # 路由配置
│   │   ├── store/         # Pinia 状态管理
│   │   ├── utils/         # 工具函数
│   │   ├── views/         # 页面组件
│   │   ├── App.vue
│   │   ├── main.js
│   │   └── style.css
│   ├── public/
│   ├── package.json
│   └── vite.config.js
├── server/                # 后端服务 (ThinkPHP 8)
│   ├── app/
│   │   ├── controller/    # 控制器
│   │   ├── model/         # 模型
│   │   ├── validate/      # 验证器
│   │   ├── middleware/    # 中间件
│   │   ├── service/       # 服务层
│   │   ├── common/        # 公共函数
│   │   └── library/       # 类库
│   ├── config/            # 配置文件
│   ├── public/            # 入口目录
│   ├── route/             # 路由定义
│   ├── runtime/           # 运行时目录
│   ├── vendor/            # Composer 依赖
│   ├── .env               # 环境变量
│   └── composer.json
├── docker/                # Docker 配置
│   ├── nginx/             # Nginx 配置
│   ├── php/               # PHP-FPM 配置
│   ├── mysql/             # MySQL 配置
│   └── redis/             # Redis 配置
├── docs/                  # 项目文档
├── docker-compose.yml     # Docker Compose 配置
└── README.md
```

## 快速开始

### 环境要求
- Docker 20.10+
- Docker Compose v2+
- Node.js 18+ (前端开发)
- Composer 2.x (PHP 依赖管理)

### 启动后端服务 (Docker)

```bash
# 进入项目根目录
cd /workspace

# 启动所有服务
docker compose up -d

# 查看服务状态
docker compose ps

# 查看日志
docker compose logs -f
```

启动后访问:
- 后端接口: http://localhost:8000
- MySQL: localhost:3306 (root / root123456)
- Redis: localhost:6379

### 启动前端开发服务

```bash
# 进入前端目录
cd /workspace/admin

# 安装依赖 (首次需要)
npm install

# 启动开发服务器
npm run dev
```

前端开发服务器默认运行在 http://localhost:5173

### 常用命令

#### 后端
```bash
# 进入 PHP 容器
docker compose exec php-fpm sh

# 执行 ThinkPHP 命令
docker compose exec php-fpm php think

# 安装 PHP 依赖
docker compose exec php-fpm composer install
```

#### 前端
```bash
# 开发模式
npm run dev

# 构建生产版本
npm run build

# 代码检查
npm run lint

# 代码格式化
npm run format
```

## 数据库配置

| 参数 | 值 |
|------|-----|
| 主机 | mysql (Docker 内部) / localhost (外部) |
| 端口 | 3306 |
| 数据库 | card_auth |
| 用户名 | root |
| 密码 | root123456 |
| 表前缀 | ca_ |

## Redis 配置

| 参数 | 值 |
|------|-----|
| 主机 | redis (Docker 内部) / localhost (外部) |
| 端口 | 6379 |
| 密码 | (无) |
| 数据库 | 0 |

## 开发规范

### 后端
- 遵循 PSR-12 编码规范
- 控制器薄，业务逻辑在 Service 层
- 使用验证器进行参数校验
- API 统一返回格式

### 前端
- 使用 Composition API + `<script setup>`
- 组件命名使用 PascalCase
- 遵循 ESLint + Prettier 代码规范
- API 请求统一封装

## License

MIT
