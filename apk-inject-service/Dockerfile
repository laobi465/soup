# syntax=docker/dockerfile:1
# 多阶段构建：APK 注入微服务（Task 5）
# 依赖外部 Android 工具：zipalign / apksigner / aapt2 / APKEditor.jar

# ---------- 1. 构建阶段 ----------
FROM maven:3.9-eclipse-temurin-17 AS builder
WORKDIR /build
# 先拷贝 pom 预取依赖，利用 Docker 层缓存
COPY pom.xml .
RUN mvn dependency:go-offline -B || true
COPY src ./src
RUN mvn package -DskipTests -B

# ---------- 2. 运行阶段 ----------
# 使用 debian 基础镜像：alpine 仓库缺少 Android build-tools，debian 更易集成
FROM eclipse-temurin:17-jre-jammy

# 安装基础工具；Android build-tools（zipalign/apksigner/aapt2）需从 Android SDK 复制或挂载
# 这里安装 openjdk 工具链中无的辅助包，并提供 APKEditor 运行所需的 java（jre 已有）
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        unzip \
        zip \
        bash \
        tini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# 拷贝构建产物
COPY --from=builder /build/target/apk-inject-service.jar app.jar

# 拷贝 dex 资源（keystore 不进镜像，通过 volume 挂载到 /opt/keystore）
COPY src/main/resources/dex /app/resources/dex

# 工具目录：APKEditor.jar 及 Android build-tools 二进制应放置于此（或挂载）
RUN mkdir -p /app/tools /tmp/apk-inject

# 说明：zipalign / apksigner / aapt2 来自 Android SDK Build Tools；
# 部署时可通过挂载 /opt/android-sdk/build-tools/<ver>/ 并加入 PATH，或直接 COPY 到 /usr/local/bin
# APKEditor.jar 需下载（https://github.com/REAndroid/APKEditor）后 COPY 到 /app/tools/APKEditor.jar

ENV JAVA_OPTS="-Xms256m -Xmx1536m -XX:+UseG1GC"
ENV SPRING_PROFILES_ACTIVE=prod

EXPOSE 8080

# tini 作为 init 进程，妥善回收子进程（外部工具 ProcessBuilder 调用）
ENTRYPOINT ["tini", "--", "sh", "-c", "exec java $JAVA_OPTS -jar /app/app.jar --spring.profiles.active=$SPRING_PROFILES_ACTIVE"]
