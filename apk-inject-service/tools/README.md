# 外部工具说明

本微服务在运行时依赖以下 Android 生态外部工具（非 Java 依赖，需在容器/宿主机中提供）。
所有路径均可通过 `application.yml` / 环境变量覆盖。

## 1. APKEditor.jar（APK 解包/重打包）

- 用途：二进制 AndroidManifest.xml 的 decode/build（`ManifestModifier` 调用）。
- 下载：https://github.com/REAndroid/APKEditor/releases
- 放置位置（默认）：`/app/tools/APKEditor.jar`
- 环境变量：`APKEDITOR_PATH`

调用方式：
```
java -jar APKEditor.jar d -i input.apk -o decoded_dir   # decode
java -jar APKEditor.jar b -i decoded_dir -o output.apk  # build
```

## 2. Android SDK Build Tools（zipalign / apksigner / aapt2）

均来自 Android SDK Build Tools，建议版本 ≥ 33。

| 工具      | 用途                                      | 默认路径 | 环境变量        |
|-----------|-------------------------------------------|----------|-----------------|
| aapt2     | `aapt2 dump badging` 解析 APK 元信息      | `aapt2`  | `AAPT2_PATH`    |
| zipalign  | APK 未压缩条目 4 字节对齐                 | `zipalign` | `ZIPALIGN_PATH` |
| apksigner | APK v1+v2+v3 签名                         | `apksigner` | `APKSIGNER_PATH` |

### 在 Docker 镜像中安装

`eclipse-temurin:17-jre-jammy` 基础镜像不带这些工具，推荐两种方式：

1. **挂载 Android SDK**（推荐生产）：
   ```yaml
   volumes:
     - /opt/android-sdk/build-tools/34.0.0:/opt/build-tools:ro
   environment:
     - ZIPALIGN_PATH=/opt/build-tools/zipalign
     - APKSIGNER_PATH=/opt/build-tools/apksigner
     - AAPT2_PATH=/opt/build-tools/aapt2
   ```
   注意：`apksigner` 是一个 shell 脚本，依赖同目录下的 `apksigner.jar` 及 `java`。

2. **构建时 COPY** 到 `/usr/local/bin`（见 `Dockerfile`）。

## 3. 平台签名 keystore

- 默认占位：`src/main/resources/keystore/platform.keystore`（开发用自签名证书）
- 别名：`platform`，口令：`changeit`（见 `application.yml`）
- **生产环境必须替换为平台正式签名证书**，通过环境变量或挂载覆盖：
  ```
  APK_KEYSTORE_PATH=/secrets/platform.keystore
  APK_KEYSTORE_PASSWORD=********
  APK_KEYSTORE_ALIAS=platform
  ```
