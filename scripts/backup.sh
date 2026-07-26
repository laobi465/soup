#!/bin/bash

set -e

BACKUP_DIR="/data/backups/mysql"
MYSQL_HOST="mysql"
MYSQL_PORT="3306"
MYSQL_USER="root"
MYSQL_PASSWORD="${MYSQL_ROOT_PASSWORD:-root123456}"
MYSQL_DATABASE="card_auth"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${MYSQL_DATABASE}_${DATE}.sql"
KEEP_DAYS=7

mkdir -p "${BACKUP_DIR}"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] 开始数据库备份..."

mysqldump -h "${MYSQL_HOST}" \
          -P "${MYSQL_PORT}" \
          -u "${MYSQL_USER}" \
          -p"${MYSQL_PASSWORD}" \
          --single-transaction \
          --routines \
          --triggers \
          --events \
          --set-gtid-purged=OFF \
          --quick \
          "${MYSQL_DATABASE}" \
          > "${BACKUP_FILE}"

if [ $? -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 备份成功: ${BACKUP_FILE}"

    gzip "${BACKUP_FILE}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 压缩完成: ${BACKUP_FILE}.gz"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 备份失败!"
    rm -f "${BACKUP_FILE}"
    exit 1
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] 清理 ${KEEP_DAYS} 天前的备份..."
find "${BACKUP_DIR}" -name "*.sql.gz" -mtime +${KEEP_DAYS} -delete
find "${BACKUP_DIR}" -name "*.sql" -mtime +${KEEP_DAYS} -delete

echo "[$(date '+%Y-%m-%d %H:%M:%S')] 备份任务完成"
