#!/bin/bash
# MinIO 初始化脚本 - 创建 bucket 和 lifecycle 规则
# 需要在 MinIO 启动后运行

set -e

MINIO_HOST="${MINIO_HOST:-http://minio:9000}"
MINIO_USER="${MINIO_ROOT_USER:-minioadmin}"
MINIO_PASS="${MINIO_ROOT_PASSWORD:-minioadmin123}"
BUCKET="${MINIO_BUCKET:-card-auth}"

echo "等待 MinIO 启动..."
until curl -s "${MINIO_HOST}/minio/health/live" > /dev/null 2>&1; do
  echo "MinIO 未就绪，等待中..."
  sleep 2
done
echo "MinIO 已启动"

# 安装 mc 客户端
if ! command -v mc &> /dev/null; then
  curl -fsSL https://dl.min.io/client/mc/release/linux-amd64/mc -o /usr/local/bin/mc
  chmod +x /usr/local/bin/mc
fi

# 配置 alias
mc alias set local "${MINIO_HOST}" "${MINIO_USER}" "${MINIO_PASS}"

# 创建 bucket
for b in "${BUCKET}" "apk-source" "apk-output" "apk-temp"; do
  if ! mc ls "local/${b}" > /dev/null 2>&1; then
    mc mb "local/${b}"
    echo "创建 bucket: ${b}"
  fi
done

# 配置 lifecycle 规则
# apk-temp: 1小时后自动删除
mc ilm add --expire-days 0 --expire-hours 1 "local/apk-temp" 2>/dev/null || true

# apk-output: 7天后自动删除
mc ilm add --expire-days 7 "local/apk-output" 2>/dev/null || true

# apk-source: 7天后自动删除
mc ilm add --expire-days 7 "local/apk-source" 2>/dev/null || true

echo "MinIO 初始化完成"
