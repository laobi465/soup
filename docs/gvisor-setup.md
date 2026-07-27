# gVisor 沙箱安装指南

## 为什么需要 gVisor

APK 注入微服务会执行 `aapt2`、`zipalign`、`apksigner`、`APKEditor.jar` 等外部工具解析和重打包**用户上传的 APK**。
这些工具历史上存在解析漏洞，一旦被恶意 APK 触发，可能逃逸到宿主机。gVisor (runsc) 提供用户态内核级隔离，
即使容器内进程被攻破也无法直接调用宿主机系统调用。

## 安装步骤（Ubuntu/Debian）

### 1. 安装 runsc

```bash
# 添加 gVisor 官方 APT 源
curl -fsSL https://gvisor.dev/archive.key | sudo gpg --dearmor -o /usr/share/keyrings/gvisor-archive-keyring.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/gvisor-archive-keyring.gpg] https://storage.googleapis.com/gvisor/releases release main" | sudo tee /etc/apt/sources.list.d/gvisor.list > /dev/null

# 安装
sudo apt-get update && sudo apt-get install -y runsc
```

### 2. 配置 Docker 使用 runsc

```bash
# 注册 runsc 为 Docker runtime
cat <<'EOF' | sudo tee /etc/docker/daemon.json
{
  "runtimes": {
    "runsc": {
      "path": "/usr/bin/runsc"
    }
  }
}
EOF

# 重启 Docker
sudo systemctl restart docker
```

### 3. 验证

```bash
# 确认 runsc 已注册
docker info | grep -A2 Runtimes

# 测试运行
docker run --runtime=runsc --rm hello-world
```

### 4. 部署

`docker-compose.prod.yml` 中 `apk-inject-service` 已配置 `runtime: runsc`。
确保宿主机已完成上述安装后，正常执行 `docker compose -f docker-compose.prod.yml up -d` 即可。

## 兜底方案：seccomp profile

若运行环境不支持 gVisor（如某些托管 Kubernetes），`docker-compose.prod.yml` 同时配置了
`deploy/seccomp-apk-inject.json` seccomp profile，限制容器仅能调用白名单系统调用，
作为 gVisor 不可用时的兜底加固。如需禁用 gVisor 仅使用 seccomp，注释掉 `runtime: runsc` 行即可。
