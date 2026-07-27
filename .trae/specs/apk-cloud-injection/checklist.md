# APK 云端注入功能 - 验证清单

## 基础设施
- [x] Checkpoint 1: docker-compose.yml 包含 MinIO 服务（端口 9000/9001）
- [x] Checkpoint 2: composer.json 包含 topthink/think-queue、guzzlehttp/guzzle、aws/aws-sdk-s3
- [x] Checkpoint 3: config/queue.php default 驱动为 redis
- [x] Checkpoint 4: config/filesystem.php 包含 minio 磁盘配置
- [x] Checkpoint 5: php.ini upload_max_filesize=100M、post_max_size=100M
- [x] Checkpoint 6: .example.env 包含 MinIO 配置项

## MinIO 存储驱动
- [x] Checkpoint 7: MinioStorage::upload 不再抛出"待实现"
- [x] Checkpoint 8: MinioStorage::delete 不再抛出"待实现"
- [x] Checkpoint 9: MinioStorage::getFileInfo 不再抛出"待实现"
- [x] Checkpoint 10: presignedUploadUrl 返回有效 URL（5 分钟过期）
- [x] Checkpoint 11: presignedDownloadUrl 返回有效 URL（1 小时过期）
- [x] Checkpoint 12: StorageService 支持 apk 扩展名白名单

## 数据库
- [x] Checkpoint 13: apk_inject_tasks 表迁移文件存在且语法正确
- [x] Checkpoint 14: 表包含所有必需字段（id/merchant_id/app_id/status/progress/error_log 等）
- [x] Checkpoint 15: 索引包含 idx_merchant_id、idx_status、idx_file_sha256（非唯一索引，允许同 SHA-256 24h 内复用上传）
- [x] Checkpoint 16: ApkInjectTask 模型类存在且可实例化

## PHP 后端
- [x] Checkpoint 17: ApkInjectController 存在且注册到 merchant 路由组
- [x] Checkpoint 18: create 接口返回 presigned URL 和 task_id
- [x] Checkpoint 19: create 接口校验并发限制（≤3 个进行中）
- [x] Checkpoint 20: create 接口校验 SHA-256 24h 去重
- [x] Checkpoint 21: list 接口返回分页数据
- [x] Checkpoint 22: detail 接口返回任务详情
- [x] Checkpoint 23: download 接口返回 presigned URL
- [x] Checkpoint 24: ApkInjectJob 正确调用 Java 微服务
- [x] Checkpoint 25: 任务状态正确流转（排队→处理中→完成/失败）
- [x] Checkpoint 26: 并发限制使用 Redis Lua 原子操作（acquireConcurrentSlot: INCR→超限 DECR 回滚；decrementConcurrent: DECR→负数 INCR 回补）

## Java 注入微服务
- [x] Checkpoint 27: /api/v1/health 健康检查返回 200
- [x] Checkpoint 28: /api/v1/inject 接口接收正确的参数
- [x] Checkpoint 29: APK 解析正确提取 AndroidManifest 信息
- [x] Checkpoint 30: 加固检测能识别 360/腾讯/梆梆等壳
- [x] Checkpoint 31: kami-sdk.dex 正确合并到 APK
- [x] Checkpoint 32: AndroidManifest 中 Application 类替换为 KamiProxyApplication
- [x] Checkpoint 33: 原 Application 类名保存在 meta-data 中
- [x] Checkpoint 34: INTERNET 权限已添加
- [x] Checkpoint 35: zipalign 对齐正确执行
- [x] Checkpoint 36: apksigner 签名 v1+v2+v3 全部有效
- [x] Checkpoint 37: 输出 APK 上传到 MinIO
- [x] Checkpoint 38: 错误处理返回详细错误信息

## 卡密 SDK dex
- [x] Checkpoint 39: kami-sdk.dex 文件存在
- [x] Checkpoint 40: dex 包含 KamiProxyApplication 类
- [x] Checkpoint 41: dex 包含 CardAuthClient 类
- [x] Checkpoint 42: dex 体积 ≤500KB
- [x] Checkpoint 43: KamiProxyApplication.attachBaseContext 中执行 MultiDex.install
- [x] Checkpoint 44: KamiProxyApplication.onCreate 中反射替换原 Application

## 前端
- [x] Checkpoint 45: APK 注入列表页正常渲染
- [x] Checkpoint 46: 创建任务页可选择应用
- [x] Checkpoint 47: 上传功能使用 presigned URL 直传 MinIO
- [x] Checkpoint 48: 状态标签正确显示（排队/处理中/完成/失败）
- [x] Checkpoint 49: 进度条正确展示
- [x] Checkpoint 50: 下载按钮链接到 presigned URL
- [x] Checkpoint 51: 进行中任务自动轮询刷新（5 秒间隔）
- [x] Checkpoint 52: 路由和菜单项已注册

## Docker 部署
- [x] Checkpoint 53: docker-compose.yml 包含 apk-inject-service 服务
- [x] Checkpoint 54: docker-compose.yml 包含 apk-queue-worker 服务
- [x] Checkpoint 55: apk-inject-service 容器内存限制 2g
- [x] Checkpoint 56: apk-inject-service 容器 CPU 限制 2 核
- [x] Checkpoint 57: apk-inject-service 容器 pids_limit 64
- [x] Checkpoint 58: apk-inject-service 容器 read_only
- [x] Checkpoint 59: 生产环境配置 gVisor (runtime: runsc)
- [x] Checkpoint 60: MinIO lifecycle 规则配置（temp 1h / output 7d）
- [x] Checkpoint 61: 平台签名 keystore 已挂载

## 集成测试
- [x] Checkpoint 62: 端到端流程完整通过（上传→注入→下载→安装）
- [x] Checkpoint 63: 注入后 APK apksigner verify 通过
- [x] Checkpoint 64: 加固 APK 被正确拒绝
- [x] Checkpoint 65: 并发限制生效
- [x] Checkpoint 66: SHA-256 去重生效
- [x] Checkpoint 67: 所有 PHP 文件语法检查通过
- [x] Checkpoint 68: 前端 npm run build 成功
- [x] Checkpoint 69: 代码已推送到 GitHub main
