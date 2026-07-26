<?php
declare (strict_types = 1);

namespace app\service;

use app\model\RiskBlacklist;
use think\facade\Cache;
use think\facade\Db;

class RiskControlService
{
    const REGISTER_IP_LIMIT = 10;
    const REGISTER_IP_WINDOW = 86400;

    const ORDER_ABNORMAL_AMOUNT = 10000;
    const ORDER_ABNORMAL_COUNT = 10;

    const API_ABNORMAL_THRESHOLD = 1000;
    const API_ABNORMAL_WINDOW = 3600;

    const CACHE_PREFIX = 'risk:';
    const REGISTER_IP_PREFIX = 'register_ip:';
    const API_RATE_PREFIX = 'api_rate:';

    public static function checkRegisterLimit(string $ip): bool
    {
        $key = self::CACHE_PREFIX . self::REGISTER_IP_PREFIX . $ip;
        $count = Cache::get($key, 0);

        if ($count >= self::REGISTER_IP_LIMIT) {
            return false;
        }

        return true;
    }

    public static function recordRegister(string $ip): void
    {
        $key = self::CACHE_PREFIX . self::REGISTER_IP_PREFIX . $ip;
        $count = Cache::get($key, 0);
        $count++;
        Cache::set($key, $count, self::REGISTER_IP_WINDOW);

        if ($count >= self::REGISTER_IP_LIMIT) {
            self::addBlacklist(
                RiskBlacklist::TYPE_IP,
                $ip,
                '注册次数超过限制',
                date('Y-m-d H:i:s', time() + 86400)
            );
        }
    }

    public static function checkOrderAbnormal(int $userId, string $ip): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $todayOrders = Db::name('orders')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->where('pay_status', 2)
            ->count();

        $todayAmount = Db::name('orders')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->where('pay_status', 2)
            ->sum('pay_amount') ?: '0';

        $isAbnormal = false;
        $reason = '';

        if ($todayOrders >= self::ORDER_ABNORMAL_COUNT) {
            $isAbnormal = true;
            $reason = '今日订单数超过限制';
        }

        if (floatval($todayAmount) >= self::ORDER_ABNORMAL_AMOUNT) {
            $isAbnormal = true;
            $reason = $reason ? $reason . '、今日交易金额超过限制' : '今日交易金额超过限制';
        }

        return [
            'is_abnormal' => $isAbnormal,
            'reason' => $reason,
            'today_orders' => $todayOrders,
            'today_amount' => $todayAmount,
        ];
    }

    public static function checkApiAbnormal(int $appId): array
    {
        $key = self::CACHE_PREFIX . self::API_RATE_PREFIX . $appId;
        $count = Cache::get($key, 0);

        $isAbnormal = $count >= self::API_ABNORMAL_THRESHOLD;

        return [
            'is_abnormal' => $isAbnormal,
            'current_count' => $count,
            'threshold' => self::API_ABNORMAL_THRESHOLD,
            'window_seconds' => self::API_ABNORMAL_WINDOW,
        ];
    }

    public static function recordApiCall(int $appId): void
    {
        $key = self::CACHE_PREFIX . self::API_RATE_PREFIX . $appId;
        $count = Cache::get($key, 0);
        $count++;
        Cache::set($key, $count, self::API_ABNORMAL_WINDOW);
    }

    public static function checkBlacklist(int $type, string $value): array
    {
        $now = date('Y-m-d H:i:s');

        $item = RiskBlacklist::where('type', $type)
            ->where('value', $value)
            ->where(function ($query) use ($now) {
                $query->whereNull('expire_time')
                    ->whereOr('expire_time', '>=', $now);
            })
            ->find();

        if ($item) {
            return [
                'is_blacklisted' => true,
                'reason' => $item->reason,
                'expire_time' => $item->expire_time,
            ];
        }

        return [
            'is_blacklisted' => false,
            'reason' => '',
            'expire_time' => null,
        ];
    }

    public static function addBlacklist(int $type, string $value, string $reason = '', ?string $expireTime = null)
    {
        $existing = RiskBlacklist::where('type', $type)
            ->where('value', $value)
            ->find();

        if ($existing) {
            $existing->reason = $reason;
            $existing->expire_time = $expireTime;
            $existing->save();
            return $existing;
        }

        $item = new RiskBlacklist();
        $item->type = $type;
        $item->value = $value;
        $item->reason = $reason;
        $item->expire_time = $expireTime;
        $item->save();

        return $item;
    }

    public static function removeBlacklist(int $id): bool
    {
        $item = RiskBlacklist::find($id);
        if (!$item) {
            return false;
        }

        $item->delete();
        return true;
    }

    public static function getBlacklist(array $filters = [], int $page = 1, int $pageSize = 10): array
    {
        $query = RiskBlacklist::order('id', 'desc');

        if (!empty($filters['type'])) {
            $query->where('type', intval($filters['type']));
        }

        if (!empty($filters['keyword'])) {
            $query->where('value', 'like', '%' . $filters['keyword'] . '%');
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $now = date('Y-m-d H:i:s');
            if ($filters['status'] == 1) {
                $query->where(function ($q) use ($now) {
                    $q->whereNull('expire_time')
                        ->whereOr('expire_time', '>=', $now);
                });
            } elseif ($filters['status'] == 0) {
                $query->where('expire_time', '<', $now);
            }
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $data = $item->toArray();
            $data['type_text'] = $item->type_text;
            $data['is_active'] = !$item->isExpired();
            $items[] = $data;
        }

        return [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];
    }

    public static function getRiskOverview(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $totalBlacklist = RiskBlacklist::count();
        $activeBlacklist = RiskBlacklist::where(function ($query) {
            $query->whereNull('expire_time')
                ->whereOr('expire_time', '>=', date('Y-m-d H:i:s'));
        })->count();

        $todayAdded = RiskBlacklist::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        $ipCount = RiskBlacklist::where('type', RiskBlacklist::TYPE_IP)->count();
        $deviceCount = RiskBlacklist::where('type', RiskBlacklist::TYPE_DEVICE)->count();
        $phoneCount = RiskBlacklist::where('type', RiskBlacklist::TYPE_PHONE)->count();
        $emailCount = RiskBlacklist::where('type', RiskBlacklist::TYPE_EMAIL)->count();

        return [
            'total_blacklist' => $totalBlacklist,
            'active_blacklist' => $activeBlacklist,
            'today_added' => $todayAdded,
            'by_type' => [
                'ip' => $ipCount,
                'device' => $deviceCount,
                'phone' => $phoneCount,
                'email' => $emailCount,
            ],
        ];
    }
}
