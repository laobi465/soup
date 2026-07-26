<?php
declare (strict_types = 1);

namespace app\library\storage\drivers;

use app\library\storage\StorageDriver;
use think\facade\Filesystem;

class LocalStorage extends StorageDriver
{
    public function upload($file, string $path): array
    {
        $disk = Filesystem::disk('public');
        $savePath = $disk->putFileAs(dirname($path), $file, basename($path));

        if (!$savePath) {
            throw new \RuntimeException('文件上传失败');
        }

        return [
            'path' => $savePath,
            'url' => $this->getUrl($savePath),
            'size' => $file->getSize(),
            'mime_type' => $file->getMime(),
        ];
    }

    public function delete(string $path): bool
    {
        $disk = Filesystem::disk('public');
        if ($disk->fileExists($path)) {
            return $disk->delete($path);
        }
        return true;
    }

    public function getUrl(string $path): string
    {
        $baseUrl = $this->config['base_url'] ?? '';
        if (empty($baseUrl)) {
            $baseUrl = request()->domain() . '/static';
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function getFileInfo(string $path): array
    {
        $disk = Filesystem::disk('public');
        if (!$disk->fileExists($path)) {
            throw new \RuntimeException('文件不存在');
        }

        return [
            'path' => $path,
            'url' => $this->getUrl($path),
            'size' => $disk->fileSize($path),
            'mime_type' => $disk->mimeType($path),
            'last_modified' => date('Y-m-d H:i:s', $disk->lastModified($path)),
        ];
    }
}
