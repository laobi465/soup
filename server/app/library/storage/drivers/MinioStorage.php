<?php
declare (strict_types = 1);

namespace app\library\storage\drivers;

use app\library\storage\StorageDriver;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MinioStorage extends StorageDriver
{
    private ?S3Client $client = null;

    /**
     * 获取 S3Client 实例（懒加载）
     */
    private function getClient(): S3Client
    {
        if ($this->client === null) {
            $this->client = new S3Client([
                'endpoint' => $this->config['endpoint'] ?? 'http://minio:9000',
                'region' => $this->config['region'] ?? 'us-east-1',
                'version' => 'latest',
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $this->config['access_key'] ?? 'minioadmin',
                    'secret' => $this->config['secret_key'] ?? '',
                ],
            ]);
        }
        return $this->client;
    }

    private function getBucket(): string
    {
        return $this->config['bucket'] ?? 'card-auth';
    }

    public function upload($file, string $path): array
    {
        try {
            $client = $this->getClient();
            $bucket = $this->getBucket();

            // 确保 bucket 存在
            if (!$client->doesBucketExist($bucket)) {
                $client->createBucket(['Bucket' => $bucket]);
            }

            $result = $client->putObject([
                'Bucket' => $bucket,
                'Key' => ltrim($path, '/'),
                'SourceFile' => is_string($file) ? $file : null,
                'Body' => is_string($file) ? null : $file,
                'ContentType' => mime_content_type(is_string($file) ? $file : '') ?: 'application/octet-stream',
            ]);

            return [
                'path' => $path,
                'url' => $this->getUrl($path),
                'size' => is_string($file) ? filesize($file) : 0,
                'etag' => $result->get('ETag') ?? '',
            ];
        } catch (AwsException $e) {
            throw new \RuntimeException('MinIO上传失败: ' . $e->getMessage());
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->getClient()->deleteObject([
                'Bucket' => $this->getBucket(),
                'Key' => ltrim($path, '/'),
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
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
        try {
            $result = $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => ltrim($path, '/'),
            ]);
            return [
                'path' => $path,
                'size' => $result->get('ContentLength') ?? 0,
                'content_type' => $result->get('ContentType') ?? '',
                'etag' => $result->get('ETag') ?? '',
                'last_modified' => $result->get('LastModified') ? $result->get('LastModified')->format('Y-m-d H:i:s') : null,
            ];
        } catch (AwsException $e) {
            return [];
        }
    }

    public function presignedUploadUrl(string $path, int $expires = 300): string
    {
        try {
            $cmd = $this->getClient()->getCommand('PutObject', [
                'Bucket' => $this->getBucket(),
                'Key' => ltrim($path, '/'),
            ]);
            $request = $this->getClient()->createPresignedRequest($cmd, '+' . $expires . ' seconds');
            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new \RuntimeException('生成上传URL失败: ' . $e->getMessage());
        }
    }

    public function presignedDownloadUrl(string $path, int $expires = 3600): string
    {
        try {
            $cmd = $this->getClient()->getCommand('GetObject', [
                'Bucket' => $this->getBucket(),
                'Key' => ltrim($path, '/'),
            ]);
            $request = $this->getClient()->createPresignedRequest($cmd, '+' . $expires . ' seconds');
            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new \RuntimeException('生成下载URL失败: ' . $e->getMessage());
        }
    }

    /**
     * 下载文件到本地
     */
    public function downloadToLocal(string $path, string $localPath): bool
    {
        try {
            $this->getClient()->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => ltrim($path, '/'),
                'SaveAs' => $localPath,
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * 从本地上传文件
     */
    public function uploadFromLocal(string $localPath, string $path): array
    {
        return $this->upload($localPath, $path);
    }
}
