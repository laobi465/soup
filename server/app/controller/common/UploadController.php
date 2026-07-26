<?php
declare (strict_types = 1);

namespace app\controller\common;

use app\BaseController;
use app\service\StorageService;

class UploadController extends BaseController
{
    public function uploadImage()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return error('请上传文件');
        }

        $extension = strtolower($file->extension());
        $allowedExts = StorageService::getAllowedImageExtensions();
        if (!in_array($extension, $allowedExts)) {
            return error('不支持的图片格式，允许的格式：' . implode(',', $allowedExts));
        }

        $maxSize = StorageService::getMaxUploadSize();
        if ($file->getSize() > $maxSize) {
            return error('文件大小超过限制，最大允许：' . round($maxSize / 1024 / 1024, 2) . 'MB');
        }

        try {
            $result = StorageService::upload($file, 'images');
            return success([
                'file_path' => $result['path'],
                'url' => $result['url'],
                'size' => $result['size'],
                'mime_type' => $result['mime_type'],
            ], '上传成功');
        } catch (\Exception $e) {
            return error('上传失败：' . $e->getMessage());
        }
    }

    public function uploadFile()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return error('请上传文件');
        }

        $extension = strtolower($file->extension());
        $allowedExts = StorageService::getAllowedFileExtensions();
        if (!in_array($extension, $allowedExts)) {
            return error('不支持的文件格式，允许的格式：' . implode(',', $allowedExts));
        }

        $maxSize = StorageService::getMaxUploadSize();
        if ($file->getSize() > $maxSize) {
            return error('文件大小超过限制，最大允许：' . round($maxSize / 1024 / 1024, 2) . 'MB');
        }

        try {
            $result = StorageService::upload($file, 'files');
            return success([
                'file_path' => $result['path'],
                'url' => $result['url'],
                'size' => $result['size'],
                'mime_type' => $result['mime_type'],
                'name' => $file->getOriginalName(),
            ], '上传成功');
        } catch (\Exception $e) {
            return error('上传失败：' . $e->getMessage());
        }
    }

    public function getUploadConfig()
    {
        return success([
            'max_size' => StorageService::getMaxUploadSize(),
            'image_exts' => StorageService::getAllowedImageExtensions(),
            'file_exts' => StorageService::getAllowedFileExtensions(),
        ]);
    }
}
