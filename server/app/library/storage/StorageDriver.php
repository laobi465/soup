<?php
declare (strict_types = 1);

namespace app\library\storage;

abstract class StorageDriver
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function upload($file, string $path): array;

    abstract public function delete(string $path): bool;

    abstract public function getUrl(string $path): string;

    abstract public function getFileInfo(string $path): array;

    public function presignedUploadUrl(string $path, int $expires = 300): string
    {
        throw new \RuntimeException('当前存储驱动不支持 presigned URL');
    }

    public function presignedDownloadUrl(string $path, int $expires = 3600): string
    {
        throw new \RuntimeException('当前存储驱动不支持 presigned URL');
    }
}
