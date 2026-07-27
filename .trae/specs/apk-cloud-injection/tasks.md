# APK 云端注入功能 - The Implementation Plan (Decomposed and Prioritized Task List)

## [x] Task 1: 基础设施搭建（MinIO + think-queue + 依赖引入）
- **Priority**: high
- **Depends On**: None
- **Description**:
  - docker-compose.yml 和 docker-compose.prod.yml 新增 MinIO 服务（端口 9000/9001）
  - composer require topthink/think-queue guzzlehttp/guzzle aws/aws-sdk-s3
  - config/queue.php 默认驱动改为 redis，新增 apk-inject 队列连接
  - config/filesystem.php 新增 minio 磁盘配置
  - docker/php/php.ini 调整 upload_max_filesize=100M、post_max_size=100M
  - .example.env 新增 MinIO 配置项
- **Acceptance Criteria Addressed**: AC-1
- **Test Requirements**:
  - `programmatic` TR-1.1: docker compose config 验证 YAML 格式正确
  - `programmatic` TR-1.2: composer.json 包含 think-queue、guzzle、aws-sdk-s3
  - `programmatic` TR-1.3: config/queue.php default 为 redis
  - `programmatic` TR-1.4: config/filesystem.php 包含 minio 磁盘配置
- **Notes**: 所有后续任务的基础

## [x] Task 2: MinioStorage 驱动实现 + presigned URL 支持
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - 实现 MinioStorage 的 upload/delete/getFileInfo 方法（使用 aws/aws-sdk-s3）
  - StorageDriver 抽象类新增 presignedUploadUrl/presignedDownloadUrl 方法（可选实现）
  - MinioStorage 实现 presigned URL 生成（上传 5 分钟过期，下载 1 小时过期）
  - StorageService 新增 getApkStorageDriver() 方法，返回 MinIO 驱动实例
  - StorageService 新增 APK 文件扩展名白名单（apk）
- **Acceptance Criteria Addressed**: AC-1, AC-6
- **Test Requirements**:
  - `programmatic` TR-2.1: MinioStorage::upload 不再抛出"待实现"
  - `programmatic` TR-2.2: presignedUploadUrl 返回有效 URL
  - `programmatic` TR-2.3: presignedDownloadUrl 返回有效 URL
- **Notes**: 复用现有 StorageDriver 抽象，扩展而非重写

## [x] Task 3: 数据库迁移与模型（apk_inject_tasks 表）
- **Priority**: high
- **Depends On**: None
- **Description**:
  - 新建迁移文件 create_apk_inject_tasks_table，字段：
    - id, merchant_id, app_id, task_no, source_path, output_path
    - file_sha256, file_size, original_filename
    - status (1排队 2处理中 3完成 4失败), progress (0-100)
    - error_log (text), sdk_config (json)
    - completed_at, created_at, updated_at
  - 索引：idx_merchant_id, idx_status, uk_file_sha256, idx_task_no
  - 新建 ApkInjectTask 模型
- **Acceptance Criteria Addressed**: AC-1, AC-5
- **Test Requirements**:
  - `programmatic` TR-3.1: 迁移文件语法正确
  - `programmatic` TR-3.2: 模型类存在且可实例化
  - `programmatic` TR-3.3: 表名前缀为 ca_
- **Notes**: 无依赖，可与 Task 1 并行

## [x] Task 4: PHP 后端 - Controller/Service/Job
- **Priority**: high
- **Depends On**: Task 1, Task 2, Task 3
- **Description**:
  - ApkInjectController（merchant 路由组）：
    - POST /api/merchant/apk-inject/create - 创建任务（生成 presigned URL + 写任务记录 + 投队列）
    - GET /api/merchant/apk-inject/list - 任务列表（分页）
    - GET /api/merchant/apk-inject/detail/:id - 任务详情
    - GET /api/merchant/apk-inject/download/:id - 生成下载 presigned URL
  - ApkInjectService：
    - createTask(): 校验并发限制(≤3)、SHA-256 去重(24h)、生成 presigned URL、写 task 表、Queue::push
    - getDownloadUrl(): 校验任务状态、生成 presigned URL
  - ApkInjectJob（队列任务类）：
    - fire(): 更新状态为处理中 → Guzzle 调 Java 微服务 → 更新状态为完成/失败
    - failed(): 记录失败日志、更新状态
  - 路由注册到 route/app.php merchant 路由组
  - 并发限制：Redis 原子计数 merchant:apk_inject:concurrent:{merchant_id}
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-5, AC-6, AC-7
- **Test Requirements**:
  - `programmatic` TR-4.1: create 接口返回 presigned URL 和 task_id
  - `programmatic` TR-4.2: 并发任务超 3 个时返回错误
  - `programmatic` TR-4.3: SHA-256 24h 内重复返回提示
  - `programmatic` TR-4.4: list 接口返回分页数据
  - `programmatic` TR-4.5: download 接口返回 presigned URL
  - `programmatic` TR-4.6: ApkInjectJob 正确调用 Java 微服务
- **Notes**: PHP 不直接处理 APK，仅编排

## [x] Task 5: Java 注入微服务（核心）
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - 新建 /workspace/apk-inject-service/ 目录（独立 Maven/Gradle 项目）
  - 框架：Spring Boot 3 + Java 17
  - 依赖：dexlib2/multidexlib2、APKEditor(ARSCLib)、apksigner(Android SDK Build Tools)
  - HTTP API：
    - POST /api/v1/inject - 接收 { task_id, source_path, app_key, app_secret, base_url }
    - GET /api/v1/health - 健康检查
  - 注入流水线：
    1. 从 MinIO 下载源 APK 到 /tmp
    2. 解析 AndroidManifest（APKEditor）
    3. 加固检测（检查 Application 类名是否为已知壳）
    4. 合并 kami-sdk.dex 到 APK（multidexlib2，追加为 classes(N+1).dex）
    5. 修改 AndroidManifest：替换 android:name 为 KamiProxyApplication，添加 meta-data 保存原类名
    6. 添加 INTERNET 权限（如缺失）
    7. 重打包（APKEditor build）
    8. zipalign 对齐
    9. apksigner 签名（v1+v2+v3，平台统一 keystore）
    10. 上传输出 APK 到 MinIO
    11. 返回 { success, output_path, error }
  - 错误处理：每个步骤捕获异常，返回详细错误信息
  - Dockerfile：基于 eclipse-temurin:17-jre，多阶段构建
- **Acceptance Criteria Addressed**: AC-2, AC-3, AC-4
- **Test Requirements**:
  - `programmatic` TR-5.1: /health 返回 200
  - `programmatic` TR-5.2: 普通APK注入后 apksigner verify 通过
  - `programmatic` TR-5.3: 注入后 Manifest 中 Application 为 KamiProxyApplication
  - `programmatic` TR-5.4: 注入后 APK 包含 kami-sdk dex
  - `programmatic` TR-5.5: 加固 APK 返回失败且错误信息明确
  - `human-judgement` TR-5.6: 注入后的 APK 在 Android 设备上可正常安装启动
- **Notes**: 最核心最复杂的任务，需 Android 逆向工程知识

## [x] Task 6: 卡密 SDK dex 编译（注入载荷）
- **Priority**: high
- **Depends On**: Task 5
- **Description**:
  - 新建 /workspace/apk-inject-service/sdk-module/ Android Library 工程
  - 基于 /workspace/sdk/java/CardAuthClient.java，新增：
    - KamiProxyApplication extends Application
    - attachBaseContext(): MultiDex.install + 启动卡密校验线程
    - onCreate(): 反射替换回原 Application（ActivityThread.mInitialApplication 等）
    - 卡密校验 UI（弹出卡密输入对话框或 WebView）
    - ActivityLifecycleCallbacks 监控
  - 编译为 kami-sdk.dex（d8 编译，仅含 SDK 类，不含 AndroidX）
  - dex 文件打包进 Java 微服务资源目录
- **Acceptance Criteria Addressed**: AC-3
- **Test Requirements**:
  - `programmatic` TR-6.1: kami-sdk.dex 文件存在且可被 dexlib2 加载
  - `programmatic` TR-6.2: dex 包含 KamiProxyApplication 类
  - `programmatic` TR-6.3: dex 体积 ≤500KB
- **Notes**: SDK 需支持从 Manifest meta-data 读取配置（appKey 等）

## [x] Task 7: 前端注入管理页面
- **Priority**: medium
- **Depends On**: Task 4
- **Description**:
  - 新增 /workspace/admin/src/views/merchant/apk-inject/index.vue - 任务列表页
  - 新增 /workspace/admin/src/views/merchant/apk-inject/create.vue - 创建任务页
  - API 模块 /workspace/admin/src/api/merchant/apkInject.js
  - 列表页：展示任务列表，含状态标签、进度条、操作按钮
  - 创建页：选择应用、上传APK（直传MinIO）、提交任务
  - 路由注册到 merchant 路由组，菜单项"APK注入"
  - 轮询刷新进行中任务的状态（每 5 秒）
- **Acceptance Criteria Addressed**: AC-1, AC-5, AC-6
- **Test Requirements**:
  - `programmatic` TR-7.1: 列表页正常渲染
  - `programmatic` TR-7.2: 创建页可选择应用并上传文件
  - `programmatic` TR-7.3: 状态标签正确显示
  - `human-judgement` TR-7.4: 交互流畅，上传体验良好
- **Notes**: 复用现有 Element Plus 组件和布局

## [x] Task 8: Docker 部署配置 + 沙箱
- **Priority**: medium
- **Depends On**: Task 5
- **Description**:
  - docker-compose.prod.yml 新增 apk-inject-service 服务
  - docker-compose.yml 新增 apk-inject-service 服务（开发环境）
  - docker-compose 新增 apk-queue-worker 服务（php think queue:work --queue=apk-inject）
  - apk-inject-service 容器配置：memory 2g, cpus 2, pids_limit 64, read_only, tmpfs /tmp
  - 生产环境 gVisor (runtime: runsc) 配置
  - MinIO lifecycle 规则：apk-temp/ 1小时清理，apk-output/ 7天清理
  - 平台签名 keystore 生成与挂载
- **Acceptance Criteria Addressed**: AC-8
- **Test Requirements**:
  - `programmatic` TR-8.1: docker compose config 验证通过
  - `programmatic` TR-8.2: apk-inject-service 容器可启动
  - `programmatic` TR-8.3: queue-worker 容器可启动
  - `programmatic` TR-8.4: health 接口可访问
- **Notes**: gVisor 需宿主机安装 runsc

## [ ] Task 9: 集成测试与验证
- **Priority**: high
- **Depends On**: Task 4, Task 5, Task 6, Task 7, Task 8
- **Description**:
  - 准备测试 APK（简单 Hello World 应用）
  - 端到端测试：上传 → 注入 → 下载 → 安装 → 卡密校验
  - 验证注入后 APK 的签名、Manifest、dex 结构
  - 验证加固 APK 被正确拒绝
  - 验证并发限制和去重逻辑
  - 全量 PHP 语法检查
  - 前端 build 验证
  - 提交代码并推送到 GitHub main
- **Acceptance Criteria Addressed**: AC-1 ~ AC-8
- **Test Requirements**:
  - `programmatic` TR-9.1: 端到端流程完整通过
  - `programmatic` TR-9.2: 所有 PHP 文件语法检查通过
  - `programmatic` TR-9.3: 前端 npm run build 成功
  - `human-judgement` TR-9.4: 注入后 APK 在真机可正常使用卡密验证
- **Notes**: 需要准备测试环境和测试 APK
