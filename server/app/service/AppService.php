<?php
declare (strict_types = 1);

namespace app\service;

use app\library\Bcrypt;
use app\library\Random;
use app\model\App;

class AppService
{
    public static function generateAppKey(): string
    {
        do {
            $appKey = Random::string(32);
            $exists = App::where('app_key', $appKey)->find();
        } while ($exists);

        return $appKey;
    }

    public static function generateAppSecret(): string
    {
        return Random::string(32);
    }

    public static function verifyAppSecret(string $appKey, string $appSecret): bool
    {
        $app = App::where('app_key', $appKey)->find();
        if (!$app) {
            return false;
        }

        return Bcrypt::verify($appSecret, $app->app_secret_hash);
    }

    public static function checkAppStatus(int $appId): bool
    {
        $app = App::find($appId);
        if (!$app) {
            return false;
        }

        return $app->status == 1;
    }

    public static function getAppByKey(string $appKey)
    {
        return App::where('app_key', $appKey)->find();
    }
}
