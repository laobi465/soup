<?php
declare (strict_types = 1);

namespace app\library;

class Random
{
    public static function string(int $length = 16, string $chars = null): string
    {
        if ($chars === null) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    public static function numeric(int $length = 6): string
    {
        return self::string($length, '0123456789');
    }

    public static function alphabet(int $length = 8): string
    {
        return self::string($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    public static function merchantNo(): string
    {
        return 'M' . date('Ymd') . self::numeric(6);
    }

    public static function inviteCode(int $length = 8): string
    {
        return strtoupper(self::string($length, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'));
    }

    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
