<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Merchant;
use app\model\Agent;
use app\model\Card;
use app\model\Order;
use app\model\ApiLog;
use app\model\Device;
use app\model\App;
use think\facade\Db;

class StatService
{
    public static function getAdminDashboard(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $weekStart = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $monthStart = date('Y-m-01 00:00:00');

        $merchantTotal = Merchant::count();
        $merchantToday = Merchant::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        $agentTotal = Agent::count();

        $cardTotal = Card::count();
        $cardToday = Card::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        $orderTotal = Order::where('pay_status', Order::STATUS_PAID)->count();
        $orderAmount = Order::where('pay_status', Order::STATUS_PAID)->sum('pay_amount') ?: '0.00';
        $platformIncome = Order::where('pay_status', Order::STATUS_PAID)->sum('platform_fee') ?: '0.00';

        $apiToday = ApiLog::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();
        $apiWeek = ApiLog::where('created_at', '>=', $weekStart)->count();
        $apiMonth = ApiLog::where('created_at', '>=', $monthStart)->count();

        $onlineDevices = Device::where('is_online', 1)
            ->where('last_heartbeat', '>=', date('Y-m-d H:i:s', time() - 180))
            ->count();

        $pendingTickets = 0;
        if (class_exists('\app\model\Ticket')) {
            $pendingTickets = \app\model\Ticket::where('status', 1)->count();
        }

        return [
            'merchant' => [
                'total' => $merchantTotal,
                'today_new' => $merchantToday,
            ],
            'agent' => [
                'total' => $agentTotal,
            ],
            'card' => [
                'total' => $cardTotal,
                'today_generated' => $cardToday,
            ],
            'order' => [
                'total' => $orderTotal,
                'amount' => $orderAmount,
                'platform_income' => $platformIncome,
            ],
            'api' => [
                'today' => $apiToday,
                'week' => $apiWeek,
                'month' => $apiMonth,
            ],
            'online_devices' => $onlineDevices,
            'pending_tickets' => $pendingTickets,
        ];
    }

    public static function getMerchantDashboard(int $merchantId): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $appCount = App::where('merchant_id', $merchantId)->count();

        $statusCounts = Card::where('merchant_id', $merchantId)
            ->field('status, COUNT(*) as count')
            ->group('status')
            ->column('count', 'status');

        $cardStats = [
            'unused' => intval($statusCounts[Card::STATUS_UNUSED] ?? 0),
            'activated' => intval($statusCounts[Card::STATUS_ACTIVATED] ?? 0),
            'expired' => intval($statusCounts[Card::STATUS_EXPIRED] ?? 0),
            'banned' => intval($statusCounts[Card::STATUS_BANNED] ?? 0),
        ];

        $merchant = Merchant::find($merchantId);
        $apiQuota = $merchant->api_quota ?? 0;
        $apiUsed = $merchant->api_used ?? 0;
        $apiRemaining = max(0, $apiQuota - $apiUsed);

        $cardIncome = Order::where('merchant_id', $merchantId)
            ->where('pay_status', Order::STATUS_PAID)
            ->where('type', Order::TYPE_SHOP)
            ->sum('pay_amount') ?: '0.00';

        $agentCount = Agent::where('merchant_id', $merchantId)->count();

        $onlineDevices = Device::alias('d')
            ->join('cards c', 'd.card_id = c.id')
            ->where('c.merchant_id', $merchantId)
            ->where('d.is_online', 1)
            ->where('d.last_heartbeat', '>=', date('Y-m-d H:i:s', time() - 180))
            ->count();

        $expiringSoon = Card::where('merchant_id', $merchantId)
            ->where('status', Card::STATUS_ACTIVATED)
            ->where('expire_time', '>=', date('Y-m-d H:i:s'))
            ->where('expire_time', '<=', date('Y-m-d H:i:s', strtotime('+7 days')))
            ->count();

        return [
            'app_count' => $appCount,
            'card_stats' => $cardStats,
            'api' => [
                'quota' => $apiQuota,
                'used' => $apiUsed,
                'remaining' => $apiRemaining,
            ],
            'card_income' => $cardIncome,
            'agent_count' => $agentCount,
            'online_devices' => $onlineDevices,
            'expiring_soon' => $expiringSoon,
        ];
    }

    public static function getAgentDashboard(int $agentId): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $agent = Agent::find($agentId);
        if (!$agent) {
            return [];
        }

        $childCount = Agent::where('parent_agent_id', $agentId)->count();
        $childToday = Agent::where('parent_agent_id', $agentId)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        $availableBalance = $agent->available_balance ?? '0.00';
        $frozenBalance = $agent->frozen_balance ?? '0.00';
        $totalCommission = bcadd($availableBalance, $frozenBalance, 2);

        $lowerLevelCount = 0;
        $subordinateAgents = Agent::where('parent_agent_id', $agentId)->column('id');
        if (!empty($subordinateAgents)) {
            $lowerLevelCount = Agent::whereIn('parent_agent_id', $subordinateAgents)->count();
        }

        $trendData = self::getAgentCommissionTrend($agentId);

        return [
            'promotion_count' => $childCount,
            'today_new' => $childToday,
            'commission' => [
                'total' => $totalCommission,
                'available' => $availableBalance,
                'frozen' => $frozenBalance,
            ],
            'lower_level_count' => $lowerLevelCount,
            'trend' => $trendData,
        ];
    }

    public static function getCardStats(int $appId): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $total = Card::where('app_id', $appId)->count();

        $statusCounts = Card::where('app_id', $appId)
            ->field('status, COUNT(*) as count')
            ->group('status')
            ->column('count', 'status');

        $unused = intval($statusCounts[Card::STATUS_UNUSED] ?? 0);
        $activated = intval($statusCounts[Card::STATUS_ACTIVATED] ?? 0);
        $expired = intval($statusCounts[Card::STATUS_EXPIRED] ?? 0);
        $banned = intval($statusCounts[Card::STATUS_BANNED] ?? 0);
        $voided = intval($statusCounts[Card::STATUS_VOIDED] ?? 0);

        $todayGenerated = Card::where('app_id', $appId)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        return [
            'total' => $total,
            'unused' => $unused,
            'activated' => $activated,
            'expired' => $expired,
            'banned' => $banned,
            'voided' => $voided,
            'today_generated' => $todayGenerated,
        ];
    }

    public static function getApiStats(int $appId, string $range = 'day'): array
    {
        $dates = [];
        $counts = [];

        switch ($range) {
            case 'week':
                $startTime = strtotime('-6 days');
                $days = 7;
                break;
            case 'month':
                $startTime = strtotime('-29 days');
                $days = 30;
                break;
            case 'day':
            default:
                $startTime = strtotime('-23 hours');
                $hours = 24;
                break;
        }

        if ($range === 'day') {
            for ($i = 0; $i < $hours; $i++) {
                $hour = date('Y-m-d H:00:00', strtotime("+{$i} hours", $startTime));
                $nextHour = date('Y-m-d H:59:59', strtotime("+{$i} hours", $startTime));
                $dates[] = date('H时', strtotime($hour));
                $counts[] = ApiLog::where('app_id', $appId)
                    ->where('created_at', '>=', $hour)
                    ->where('created_at', '<=', $nextHour)
                    ->count();
            }
        } else {
            for ($i = 0; $i < $days; $i++) {
                $day = date('Y-m-d 00:00:00', strtotime("+{$i} days", $startTime));
                $nextDay = date('Y-m-d 23:59:59', strtotime("+{$i} days", $startTime));
                $dates[] = date('m-d', strtotime($day));
                $counts[] = ApiLog::where('app_id', $appId)
                    ->where('created_at', '>=', $day)
                    ->where('created_at', '<=', $nextDay)
                    ->count();
            }
        }

        return [
            'dates' => $dates,
            'counts' => $counts,
        ];
    }

    public static function getAdminTrend(string $range = 'week'): array
    {
        $dates = [];
        $merchantData = [];
        $orderData = [];
        $apiData = [];

        switch ($range) {
            case 'month':
                $startTime = strtotime('-29 days');
                $days = 30;
                break;
            case 'week':
            default:
                $startTime = strtotime('-6 days');
                $days = 7;
                break;
        }

        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d 00:00:00', strtotime("+{$i} days", $startTime));
            $nextDay = date('Y-m-d 23:59:59', strtotime("+{$i} days", $startTime));
            $dates[] = date('m-d', strtotime($day));

            $merchantData[] = Merchant::where('created_at', '>=', $day)
                ->where('created_at', '<=', $nextDay)
                ->count();

            $orderData[] = Order::where('pay_status', Order::STATUS_PAID)
                ->where('created_at', '>=', $day)
                ->where('created_at', '<=', $nextDay)
                ->sum('pay_amount') ?: '0';

            $apiData[] = ApiLog::where('created_at', '>=', $day)
                ->where('created_at', '<=', $nextDay)
                ->count();
        }

        return [
            'dates' => $dates,
            'merchants' => $merchantData,
            'orders' => $orderData,
            'api_calls' => $apiData,
        ];
    }

    protected static function getAgentCommissionTrend(int $agentId): array
    {
        $dates = [];
        $amounts = [];

        $startTime = strtotime('-6 days');
        $days = 7;

        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d 00:00:00', strtotime("+{$i} days", $startTime));
            $nextDay = date('Y-m-d 23:59:59', strtotime("+{$i} days", $startTime));
            $dates[] = date('m-d', strtotime($day));

            $amount = 0;
            if (class_exists('\app\model\WalletTransaction')) {
                $amount = \app\model\WalletTransaction::where('user_id', function ($query) use ($agentId) {
                    $query->name('agents')->where('id', $agentId)->value('user_id');
                })
                    ->where('type', 'commission')
                    ->where('created_at', '>=', $day)
                    ->where('created_at', '<=', $nextDay)
                    ->sum('amount') ?: '0';
            }
            $amounts[] = $amount;
        }

        return [
            'dates' => $dates,
            'amounts' => $amounts,
        ];
    }
}
