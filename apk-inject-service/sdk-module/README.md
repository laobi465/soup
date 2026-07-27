# kami-sdk 模块（注入载荷）

本模块编译产出 `kami-sdk.dex`，作为注入流水线（Task 5 的 `DexMerger`）追加到目标 APK 的
`classes(N+1).dex`。`ManifestModifier` 会把目标 APK 的 `<application android:name>` 替换为
`com.cardauth.sdk.KamiProxyApplication`，并把原 Application 类名与卡密配置写入 meta-data。

## 目录结构

```
sdk-module/
├── src/main/java/com/cardauth/sdk/
│   ├── KamiProxyApplication.java     # 代理 Application（核心）
│   ├── CardAuthClient.java           # 卡密校验客户端（org.json，零外部依赖）
│   ├── model/
│   │   ├── ApiResponse.java
│   │   └── CardVerifyResult.java
│   ├── utils/
│   │   └── SignUtil.java
│   └── ui/
│       └── CardVerifyActivity.java   # 卡密验证 UI（简化版）
├── AndroidManifest.xml               # SDK 模块 Manifest
└── build.gradle                      # 构建配置（用于编译 dex）
```

## 设计约束

- **不依赖 AndroidX**：保证最大兼容性，`MultiDex` 通过 `KamiProxyApplication` 反射调用，
  找不到则静默跳过。
- **零外部依赖**：移除原 SDK 的 Gson，改用 Android 自带的 `org.json`；HTTP 用
  `java.net.HttpURLConnection`；签名用 `javax.crypto`。这样 `kami-sdk.dex` 体积小，且不会
  与宿主 APK 的依赖版本冲突。
- **配置来源**：所有配置（appKey / appSecret / baseUrl / 原 Application 类名）从
  `AndroidManifest.xml` 的 `<meta-data>` 读取，key 为：
  `kami_original_application` / `kami_app_key` / `kami_app_secret` / `kami_base_url`。

## meta-data 读取约定

| meta-data name            | 含义                          |
|---------------------------|-------------------------------|
| `kami_original_application` | 原 Application 全限定名      |
| `kami_app_key`            | 卡密平台分配的 appKey         |
| `kami_app_secret`         | 卡密平台分配的 appSecret      |
| `kami_base_url`           | 卡密平台 API 基地址           |

## 编译为 dex

实际的 dex 编译需要在有 Android SDK 环境的地方执行。下面给出两种方式。

### 方法 1：Android Studio / Gradle 编译

```bash
cd sdk-module
./gradlew assembleRelease
# 用 d8 把 classes.jar / classes 转为 dex
d8 --output kami-sdk.dex \
    build/intermediates/javac/release/classes/com/cardauth/sdk/*.class \
    build/intermediates/javac/release/classes/com/cardauth/sdk/model/*.class \
    build/intermediates/javac/release/classes/com/cardauth/sdk/utils/*.class \
    build/intermediates/javac/release/classes/com/cardauth/sdk/ui/*.class
```

> 注意：d8 默认输出名为 `classes.dex`，可用 `--output kami-sdk.dex` 指定单文件输出路径。

### 方法 2：javac + d8 手动编译

```bash
# 1. 准备 android.jar（来自 Android SDK platforms/android-34/）
ANDROID_JAR=$ANDROID_HOME/platforms/android-34/android.jar

# 2. javac 编译（注意要编译全部包，且按依赖顺序）
javac -source 17 -target 17 \
      -classpath "$ANDROID_JAR" \
      -d classes \
      src/main/java/com/cardauth/sdk/model/*.java \
      src/main/java/com/cardauth/sdk/utils/*.java \
      src/main/java/com/cardauth/sdk/*.java \
      src/main/java/com/cardauth/sdk/ui/*.java

# 3. d8 转换为 dex（--lib 提供 android.jar 作为平台库引用）
d8 --lib "$ANDROID_JAR" --output . classes/com/cardauth/sdk/**/*.class
# 产物为 classes.dex，重命名即可
mv classes.dex kami-sdk.dex
```

### 产物放置

编译后的 `kami-sdk.dex` 复制到 Java 注入微服务的资源目录：

```bash
cp kami-sdk.dex /workspace/apk-inject-service/src/main/resources/dex/kami-sdk.dex
```

`DexMerger` 会读取该文件，校验 dex 魔数（`dex\n`）后追加到目标 APK。

## 验收对应

- TR-6.1 `kami-sdk.dex` 存在且可被 dexlib2 加载 —— 依赖本模块编译产物
- TR-6.2 dex 包含 `KamiProxyApplication` 类 —— 本模块源码已包含
- TR-6.3 dex 体积 ≤500KB —— 零外部依赖，预期远低于此阈值
