# APK 云端注入功能 - Product Requirement Document

## Overview
- **Summary**: 为网络验证SaaS平台新增"云端APK注入"核心增值功能。商户上传 Android APK 安装包，系统在云端自动注入卡密验证 SDK（Application 替换法 + dex 合并），注入后重签名返回，实现"零源码接入"卡密验证。
- **Purpose**: 降低 APP 接入卡密验证的门槛。当前需要开发者手动集成 SDK 并改源码，本功能让用户上传编译好的 APK 即可自动接入，对标 b6w.top/极简云等竞品并超越其云端自动化能力。
- **Target Users**: 平台商户（APP 开发者/运营者），拥有 APK 合法修改权

## Goals
- 用户上传 APK 后全自动完成 SDK 注入，无需改源码
- 支持普通 APK（无加固）的 Application 替换 + dex 合并注入
- 异步处理（队列+Worker），不阻塞主服务
- 大文件直传（MinIO presigned URL），不经过 PHP
- 注入后的 APK 可直接下载安装
- 提供注入任务状态查询和进度展示

## Non-Goals (Out of Scope)
- iOS IPA 注入（需 Mac 环境，后续迭代）
- HarmonyOS HAP 包注入
- 加壳 APK 的非侵入式注入（MVP 仅检测并拒绝）
- APK 在线加固/混淆功能
- Frida Gadget 运行时注入
- APK 永久存储（MVP 保留 7 天后自动清理）
- 每商户独立 keystore（MVP 用平台统一签名）

## Background & Context
- **现有项目**: ThinkPHP 8 网络验证SaaS平台，含商户后台、卡密管理、支付系统
- **现有 SDK 资产**: `/sdk/java/` 下已有完整的 Java 卡密验证 SDK（CardAuthClient），封装了 verify/activate/rebind/heartbeat 等接口，正是需要注入到用户 APK 的核心载荷
- **技术调研结论**: 注入必须用 Java 工具链（dexlib2 + APKEditor + apksigner），PHP 不直接 exec 外部命令，通过 Java 微服务 + 队列编排实现
- **现有基础设施缺口**: MinioStorage 是占位实现；composer.json 未引入 think-queue；docker-compose 无 MinIO 服务

## Functional Requirements

- **FR-1**: 商户可在后台创建注入任务，上传 APK 文件（限制 ≤100MB）
- **FR-2**: 系统通过 MinIO presigned URL 实现浏览器直传，不经 PHP 中转
- **FR-3**: 上传成功后自动创建注入任务记录，状态为"排队中"
- **FR-4**: 系统通过 Redis 队列异步投递注入任务给 Worker
- **FR-5**: Worker 调用 Java 注入微服务执行注入流程
- **FR-6**: Java 微服务执行完整注入流水线：APK解析 → 加固检测 → dex合并 → Manifest修改 → 重打包 → zipalign → 签名
- **FR-7**: 注入使用的卡密 SDK 配置（appKey/appSecret/baseUrl）从商户应用配置中自动读取
- **FR-8**: 注入完成后 APK 上传至 MinIO 输出桶，任务状态更新为"完成"
- **FR-9**: 注入失败时记录错误日志，任务状态更新为"失败"，用户可查看错误原因
- **FR-10**: 用户可在任务列表查看所有注入任务及状态
- **FR-11**: 用户可通过 presigned URL 下载注入后的 APK
- **FR-12**: 系统对 APK 进行前置安全检测：加固检测、ZIP炸弹检测、体积校验
- **FR-13**: 同一 APK（SHA-256 相同）24小时内重复提交做去重提示

## Non-Functional Requirements

- **NFR-1**: 注入任务从提交到完成 ≤3 分钟（100MB 以内 APK）
- **NFR-2**: 大文件上传不占用 PHP-FPM worker（直传 MinIO）
- **NFR-3**: Java 微服务运行在 gVisor 沙箱容器中，隔离不可信 APK
- **NFR-4**: 临时文件处理完成后 1 小时内自动清理
- **NFR-5**: 输出 APK 保留 7 天后自动清理
- **NFR-6**: 单商户并发注入任务 ≤3 个（防滥用）
- **NFR-7**: Java 微服务内存限制 2GB，CPU 限制 2 核
- **NFR-8**: 所有注入操作记录审计日志

## Constraints
- **Technical**: PHP 8.2 + ThinkPHP 8 后端；Java 17 微服务；MinIO S3 兼容存储；Docker 部署
- **Business**: 用户必须声明拥有 APK 合法修改权；注入后签名变更，部分第三方 SDK（微信/支付宝/地图）可能失效
- **Dependencies**: dexlib2/multidexlib2、APKEditor、apksigner、zipalign、d8、think-queue、guzzlehttp/guzzle、aws/aws-sdk-s3

## Assumptions
- 用户上传的 APK 为自有或已获授权修改的应用
- MVP 阶段仅处理普通 APK（无加固），加壳 APK 检测后拒绝
- 平台统一签名证书（后续迭代支持每商户独立 keystore）
- 注入的卡密 SDK 使用商户已创建的应用的 appKey/appSecret

## Acceptance Criteria

### AC-1: APK 上传
- **Given**: 商户已登录后台且已创建至少一个应用
- **When**: 商户选择 APK 文件（≤100MB）并提交注入任务
- **Then**: 文件通过 presigned URL 直传 MinIO，任务记录创建成功，返回 task_id
- **Verification**: `programmatic`

### AC-2: 注入任务异步处理
- **Given**: 注入任务已创建，状态为"排队中"
- **When**: Worker 消费到任务
- **Then**: 调用 Java 微服务执行注入，任务状态依次变为"处理中"→"完成"或"失败"
- **Verification**: `programmatic`

### AC-3: 注入结果正确性
- **Given**: 注入任务完成
- **When**: 下载注入后的 APK 并用 apksigner 验证
- **Then**: 签名有效（v1+v2+v3），AndroidManifest 中 Application 类已替换为 KamiProxyApplication，包含 kami-sdk dex
- **Verification**: `programmatic`

### AC-4: 加固 APK 拒绝
- **Given**: 用户上传了 360 加固的 APK
- **When**: Java 微服务执行前置检测
- **Then**: 任务状态变为"失败"，错误信息提示"检测到加固，暂不支持"
- **Verification**: `programmatic`

### AC-5: 任务状态查询
- **Given**: 商户有注入任务
- **When**: 调用任务列表接口
- **Then**: 返回任务列表含状态、进度、创建时间、错误信息
- **Verification**: `programmatic`

### AC-6: 下载注入后 APK
- **Given**: 注入任务已完成
- **When**: 商户点击下载
- **Then**: 返回 presigned URL，浏览器直接从 MinIO 下载
- **Verification**: `programmatic`

### AC-7: 并发限制
- **Given**: 商户已有 3 个进行中的注入任务
- **When**: 商户提交第 4 个任务
- **Then**: 返回错误"并发任务数超限，请等待已有任务完成"
- **Verification**: `programmatic`

### AC-8: 临时文件清理
- **Given**: 注入任务完成或失败
- **When**: 任务结束 1 小时后
- **Then**: MinIO apk-temp 桶中相关临时文件被清理
- **Verification**: `programmatic`

## Open Questions
- [ ] think-queue 对 ThinkPHP 8 的兼容性需实测验证（官方包 vs 社区 fork）
- [ ] 平台统一签名证书的密码管理方案（环境变量 vs 数据库加密存储）
- [ ] 是否需要支持商户自定义注入配置（如注入点选择、SDK 版本）
- [ ] Java 微服务框架选择（Spring Boot vs Javalin 轻量方案）
