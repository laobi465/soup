<?php
declare (strict_types = 1);

namespace app\library\storage\drivers;

use app\library\storage\StorageDriver;

class CosStorage extends StorageDriver
{
    public function upload($file, string $path): array
    {
        throw new \RuntimeException('腾讯云COS驱动待实现');
    }

    public function delete(string $path): bool
    {
        throw new \RuntimeException('腾讯云COS驱动待实现');
    }

    public function getUrl(string $path): string
    {
        $bucket = $this->config['bucket'] ?? '';
        $region = $this->config['region'] ?? '';
        if (empty($bucket) || empty($region)) {
            return '';
        }
        return 'https://' . $bucket . '.cos.' . $region . '.myqcloud.com/' . ltrim($path, '/');
    }

    public function getFileInfo(string $path): array
    {
        throw new \RuntimeException('腾讯云COS驱动待实现');
    }
}
