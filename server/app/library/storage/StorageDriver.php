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
}
