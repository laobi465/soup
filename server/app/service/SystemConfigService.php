<?php
declare (strict_types = 1);

namespace app\service;

use app\model\SystemConfig;
use think\facade\Cache;

class SystemConfigService
{
    const CACHE_KEY = 'system_configs';
    const CACHE_TTL = 86400;

    public static function get(string $key, $default = null)
    {
        $configs = self::all();
        return $configs[$key] ?? $default;
    }

    public static function set(string $key, $value): bool
    {
        $config = SystemConfig::where('config_key', $key)->find();
        if (!$config) {
            return false;
        }

        $configType = $config->config_type;
        $configValue = $value;

        if ($configType === 'json') {
            $configValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        } else {
            $configValue = (string)$value;
        }

        $config->config_value = $configValue;
        $result = $config->save();

        if ($result) {
            self::clearCache();
        }

        return (bool)$result;
    }

    public static function getByGroup(string $group): array
    {
        $configs = SystemConfig::where('group_name', $group)
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = self::parseValue($config['config_value'], $config['config_type']);
        }

        return $result;
    }

    public static function all(): array
    {
        $cacheData = Cache::get(self::CACHE_KEY);
        if ($cacheData !== null) {
            return $cacheData;
        }

        $configs = SystemConfig::where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = self::parseValue($config['config_value'], $config['config_type']);
        }

        Cache::set(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }

    public static function getGroupList(): array
    {
        $configs = SystemConfig::where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        $groups = [];
        foreach ($configs as $config) {
            $group = $config['group_name'];
            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][] = [
                'key' => $config['config_key'],
                'value' => self::parseValue($config['config_value'], $config['config_type']),
                'type' => $config['config_type'],
                'remark' => $config['remark'],
                'sort' => $config['sort'],
            ];
        }

        return $groups;
    }

    public static function saveBatch(array $data): bool
    {
        $configs = SystemConfig::whereIn('config_key', array_keys($data))
            ->select();

        foreach ($configs as $config) {
            $key = $config->config_key;
            if (!isset($data[$key])) {
                continue;
            }

            $value = $data[$key];
            $configType = $config->config_type;

            if ($configType === 'json') {
                $config->config_value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            } else {
                $config->config_value = (string)$value;
            }

            $config->save();
        }

        self::clearCache();
        return true;
    }

    public static function clearCache(): bool
    {
        return Cache::delete(self::CACHE_KEY);
    }

    protected static function parseValue($value, string $type)
    {
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'bool':
                return (bool)$value;
            case 'json':
                return json_decode($value, true) ?? [];
            case 'array':
                return json_decode($value, true) ?? [];
            default:
                return (string)$value;
        }
    }
}
