<?php
declare (strict_types = 1);

namespace app\library\storage\drivers;

use app\library\storage\StorageDriver;

class MinioStorage extends StorageDriver
{
    public function upload($file, string $path): array
    {
        throw new \RuntimeException('MinIO驱动待实现');
    }

    public function delete(string $path): bool
    {
        throw new \RuntimeException('MinIO驱动待实现');
    }

    public function getUrl(string $path): string
    {
        $endpoint = $this->config['endpoint'] ?? '';
        $bucket = $this->config['bucket'] ?? '';
        if (empty($endpoint) || empty($bucket)) {
            return '';
        }
        return rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($path, '/');
    }

    public function getFileInfo(string $path): array
    {
        throw new \RuntimeException('MinIO驱动待实现');
    }
}
