<?php
declare (strict_types = 1);

namespace app\library;

class AesEncrypt
{
    protected static string $method = 'AES-256-CBC';

    protected static function getKey(): string
    {
        $key = config('app.app_secret_key', env('APP_SECRET_KEY', ''));
        if (empty($key) || strlen($key) < 16) {
            throw new \RuntimeException('AES secret key must be configured and at least 16 characters long');
        }
        return hash('sha256', $key, true);
    }

    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::$method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($data, self::$method, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data): string|false
    {
        $key = self::getKey();
        $data = base64_decode($data);
        if ($data === false) {
            return false;
        }
        $ivLength = openssl_cipher_iv_length(self::$method);
        if (strlen($data) < $ivLength) {
            return false;
        }
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, self::$method, $key, OPENSSL_RAW_DATA, $iv);
    }
}
