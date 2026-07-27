<?php
declare (strict_types = 1);

namespace app\service;

use app\library\storage\drivers\LocalStorage;
use app\library\storage\drivers\OssStorage;
use app\library\storage\drivers\CosStorage;
use app\library\storage\drivers\QiniuStorage;
use app\library\storage\drivers\MinioStorage;
use app\library\storage\StorageDriver;

class StorageService
{
    const DRIVER_LOCAL = 'local';
    const DRIVER_OSS = 'oss';
    const DRIVER_COS = 'cos';
    const DRIVER_QINIU = 'qiniu';
    const DRIVER_MINIO = 'minio';

    protected static ?StorageDriver $driver = null;

    public static function getDriver(): StorageDriver
    {
        if (self::$driver !== null) {
            return self::$driver;
        }

        $driverType = SystemConfigService::get('storage_driver', self::DRIVER_LOCAL);
        $config = self::getDriverConfig($driverType);

        self::$driver = self::createDriver($driverType, $config);

        return self::$driver;
    }

    public static function setDriver(string $driverType): void
    {
        $config = self::getDriverConfig($driverType);
        self::$driver = self::createDriver($driverType, $config);
    }

    protected static function createDriver(string $driverType, array $config): StorageDriver
    {
        switch ($driverType) {
            case self::DRIVER_LOCAL:
                return new LocalStorage($config);
            case self::DRIVER_OSS:
                return new OssStorage($config);
            case self::DRIVER_COS:
                return new CosStorage($config);
            case self::DRIVER_QINIU:
                return new QiniuStorage($config);
            case self::DRIVER_MINIO:
                return new MinioStorage($config);
            default:
                throw new \RuntimeException('未知的存储驱动类型');
        }
    }

    protected static function getDriverConfig(string $driverType): array
    {
        $configs = SystemConfigService::getByGroup('storage');
        $config = [];

        switch ($driverType) {
            case self::DRIVER_LOCAL:
                $config['base_url'] = $configs['storage_local_base_url'] ?? '';
                break;
            case self::DRIVER_OSS:
                $config['access_key_id'] = $configs['storage_oss_access_key_id'] ?? '';
                $config['access_key_secret'] = $configs['storage_oss_access_key_secret'] ?? '';
                $config['bucket'] = $configs['storage_oss_bucket'] ?? '';
                $config['endpoint'] = $configs['storage_oss_endpoint'] ?? '';
                $config['region'] = $configs['storage_oss_region'] ?? '';
                break;
            case self::DRIVER_COS:
                $config['secret_id'] = $configs['storage_cos_secret_id'] ?? '';
                $config['secret_key'] = $configs['storage_cos_secret_key'] ?? '';
                $config['bucket'] = $configs['storage_cos_bucket'] ?? '';
                $config['region'] = $configs['storage_cos_region'] ?? '';
                break;
            case self::DRIVER_QINIU:
                $config['access_key'] = $configs['storage_qiniu_access_key'] ?? '';
                $config['secret_key'] = $configs['storage_qiniu_secret_key'] ?? '';
                $config['bucket'] = $configs['storage_qiniu_bucket'] ?? '';
                $config['domain'] = $configs['storage_qiniu_domain'] ?? '';
                break;
            case self::DRIVER_MINIO:
                $config['endpoint'] = $configs['storage_minio_endpoint'] ?? '';
                $config['access_key'] = $configs['storage_minio_access_key'] ?? '';
                $config['secret_key'] = $configs['storage_minio_secret_key'] ?? '';
                $config['bucket'] = $configs['storage_minio_bucket'] ?? '';
                $config['use_ssl'] = $configs['storage_minio_use_ssl'] ?? false;
                break;
        }

        return $config;
    }

    public static function upload($file, string $dir = 'uploads'): array
    {
        $driver = self::getDriver();

        $extension = $file->extension();
        $fileName = date('Ymd') . '/' . md5(uniqid((string)mt_rand(), true)) . '.' . $extension;
        $path = $dir . '/' . $fileName;

        $result = $driver->upload($file, $path);

        return $result;
    }

    public static function delete(string $path): bool
    {
        $driver = self::getDriver();
        return $driver->delete($path);
    }

    public static function getUrl(string $path): string
    {
        $driver = self::getDriver();
        return $driver->getUrl($path);
    }

    public static function getFileInfo(string $path): array
    {
        $driver = self::getDriver();
        return $driver->getFileInfo($path);
    }

    public static function getMaxUploadSize(): int
    {
        return (int)SystemConfigService::get('upload_max_size', 10485760);
    }

    public static function getAllowedImageExtensions(): array
    {
        $exts = SystemConfigService::get('upload_image_exts', 'jpg,jpeg,png,gif,bmp,webp');
        return array_map('strtolower', explode(',', $exts));
    }

    public static function getAllowedFileExtensions(): array
    {
        $exts = SystemConfigService::get('upload_file_exts', 'zip,rar,7z,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,apk');
        return array_map('strtolower', explode(',', $exts));
    }

    /**
     * 获取APK存储驱动（MinIO）
     */
    public static function getApkStorageDriver(): MinioStorage
    {
        $config = config('filesystem.disks.minio', []);
        return new MinioStorage($config);
    }

    /**
     * 生成APK上传的presigned URL
     */
    public static function getApkPresignedUploadUrl(string $path, int $expires = 300): string
    {
        return self::getApkStorageDriver()->presignedUploadUrl($path, $expires);
    }

    /**
     * 生成APK下载的presigned URL
     */
    public static function getApkPresignedDownloadUrl(string $path, int $expires = 3600): string
    {
        return self::getApkStorageDriver()->presignedDownloadUrl($path, $expires);
    }
}
