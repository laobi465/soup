<?php
declare (strict_types = 1);

namespace app\library\storage\drivers;

use app\library\storage\StorageDriver;

class OssStorage extends StorageDriver
{
    public function upload($file, string $path): array
    {
        throw new \RuntimeException('阿里云OSS驱动待实现');
    }

    public function delete(string $path): bool
    {
        throw new \RuntimeException('阿里云OSS驱动待实现');
    }

    public function getUrl(string $path): string
    {
        $bucket = $this->config['bucket'] ?? '';
        $endpoint = $this->config['endpoint'] ?? '';
        if (empty($bucket) || empty($endpoint)) {
            return '';
        }
        return 'https://' . $bucket . '.' . $endpoint . '/' . ltrim($path, '/');
    }

    public function getFileInfo(string $path): array
    {
        throw new \RuntimeException('阿里云OSS驱动待实现');
    }
}
