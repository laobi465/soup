# APK 云端注入功能 - 验证清单

## 基础设施
- [ ] Checkpoint 1: docker-compose.yml 包含 MinIO 服务（端口 9000/9001）
- [ ] Checkpoint 2: composer.json 包含 topthink/think-queue、guzzlehttp/guzzle、aws/aws-sdk-s3
- [ ] Checkpoint 3: config/queue.php default 驱动为 redis
- [ ] Checkpoint 4: config/filesystem.php 包含 minio 磁盘配置
- [ ] Checkpoint 5: php.ini upload_max_filesize=100M、post_max_size=100M
- [ ] Checkpoint 6: .example.env 包含 MinIO 配置项

## MinIO 存储驱动
- [ ] Checkpoint 7: MinioStorage::upload 不再抛出"待实现"
- [ ] Checkpoint 8: MinioStorage::delete 不再抛出"待实现"
- [ ] Checkpoint 9: MinioStorage::getFileInfo 不再抛出"待实现"
- [ ] Checkpoint 10: presignedUploadUrl 返回有效 URL（5 分钟过期）
- [ ] Checkpoint 11: presignedDownloadUrl 返回有效 URL（1 小时过期）
- [ ] Checkpoint 12: StorageService 支持 apk 扩展名白名单

## 数据库
- [ ] Checkpoint 13: apk_inject_tasks 表迁移文件存在且语法正确
- [ ] Checkpoint 14: 表包含所有必需字段（id/merchant_id/app_id/status/progress/error_log 等）
- [ ] Checkpoint 15: 索引包含 idx_merchant_id、idx_status、uk_file_sha256
- [ ] Checkpoint 16: ApkInjectTask 模型类存在且可实例化

## PHP 后端
- [ ] Checkpoint 17: ApkInjectController 存在且注册到 merchant 路由组
- [ ] Checkpoint 18: create 接口返回 presigned URL 和 task_id
- [ ] Checkpoint 19: create 接口校验并发限制（≤3 个进行中）
- [ ] Checkpoint 20: create 接口校验 SHA-256 24h 去重
- [ ] Checkpoint 21: list 接口返回分页数据
- [ ] Checkpoint 22: detail 接口返回任务详情
- [ ] Checkpoint 23: download 接口返回 presigned URL
- [ ] Checkpoint 24: ApkInjectJob 正确调用 Java 微服务
- [ ] Checkpoint 25: 任务状态正确流转（排队→处理中→完成/失败）
- [ ] Checkpoint 26: 并发限制使用 Redis 原子计数

## Java 注入微服务
- [ ] Checkpoint 27: /api/v1/health 健康检查返回 200
- [ ] Checkpoint 28: /api/v1/inject 接口接收正确的参数
- [ ] Checkpoint 29: APK 解析正确提取 AndroidManifest 信息
- [ ] Checkpoint 30: 加固检测能识别 360/腾讯/梆梆等壳
- [ ] Checkpoint 31: kami-sdk.dex 正确合并到 APK
- [ ] Checkpoint 32: AndroidManifest 中 Application 类替换为 KamiProxyApplication
- [ ] Checkpoint 33: 原 Application 类名保存在 meta-data 中
- [ ] Checkpoint 34: INTERNET 权限已添加
- [ ] Checkpoint 35: zipalign 对齐正确执行
- [ ] Checkpoint 36: apksigner 签名 v1+v2+v3 全部有效
- [ ] Checkpoint 37: 输出 APK 上传到 MinIO
- [ ] Checkpoint 38: 错误处理返回详细错误信息

## 卡密 SDK dex
- [ ] Checkpoint 39: kami-sdk.dex 文件存在
- [ ] Checkpoint 40: dex 包含 KamiProxyApplication 类
- [ ] Checkpoint 41: dex 包含 CardAuthClient 类
- [ ] Checkpoint 42: dex 体积 ≤500KB
- [ ] Checkpoint 43: KamiProxyApplication.attachBaseContext 中执行 MultiDex.install
- [ ] Checkpoint 44: KamiProxyApplication.onCreate 中反射替换原 Application

## 前端
- [ ] Checkpoint 45: APK 注入列表页正常渲染
- [ ] Checkpoint 46: 创建任务页可选择应用
- [ ] Checkpoint 47: 上传功能使用 presigned URL 直传 MinIO
- [ ] Checkpoint 48: 状态标签正确显示（排队/处理中/完成/失败）
- [ ] Checkpoint 49: 进度条正确展示
- [ ] Checkpoint 50: 下载按钮链接到 presigned URL
- [ ] Checkpoint 51: 进行中任务自动轮询刷新（5 秒间隔）
- [ ] Checkpoint 52: 路由和菜单项已注册

## Docker 部署
- [ ] Checkpoint 53: docker-compose.yml 包含 apk-inject-service 服务
- [ ] Checkpoint 54: docker-compose.yml 包含 apk-queue-worker 服务
- [ ] Checkpoint 55: apk-inject-service 容器内存限制 2g
- [ ] Checkpoint 56: apk-inject-service 容器 CPU 限制 2 核
- [ ] Checkpoint 57: apk-inject-service 容器 pids_limit 64
- [ ] Checkpoint 58: apk-inject-service 容器 read_only
- [ ] Checkpoint 59: 生产环境配置 gVisor (runtime: runsc)
- [ ] Checkpoint 60: MinIO lifecycle 规则配置（temp 1h / output 7d）
- [ ] Checkpoint 61: 平台签名 keystore 已挂载

## 集成测试
- [ ] Checkpoint 62: 端到端流程完整通过（上传→注入→下载→安装）
- [ ] Checkpoint 63: 注入后 APK apksigner verify 通过
- [ ] Checkpoint 64: 加固 APK 被正确拒绝
- [ ] Checkpoint 65: 并发限制生效
- [ ] Checkpoint 66: SHA-256 去重生效
- [ ] Checkpoint 67: 所有 PHP 文件语法检查通过
- [ ] Checkpoint 68: 前端 npm run build 成功
- [ ] Checkpoint 69: 代码已推送到 GitHub main
