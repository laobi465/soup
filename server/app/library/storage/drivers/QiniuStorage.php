<?php
declare (strict_types = 1);

namespace app\library\storage\drivers;

use app\library\storage\StorageDriver;

class QiniuStorage extends StorageDriver
{
    public function upload($file, string $path): array
    {
        throw new \RuntimeException('七牛云驱动待实现');
    }

    public function delete(string $path): bool
    {
        throw new \RuntimeException('七牛云驱动待实现');
    }

    public function getUrl(string $path): string
    {
        $domain = $this->config['domain'] ?? '';
        if (empty($domain)) {
            return '';
        }
        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    public function getFileInfo(string $path): array
    {
        throw new \RuntimeException('七牛云驱动待实现');
    }
}
