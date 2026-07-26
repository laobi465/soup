<?php
declare (strict_types = 1);

namespace app\service;

use app\model\ShopProduct;
use app\model\Order;
use think\facade\Cache;

class PurchaseLimitService
{
    protected function getUserKey(int $productId, int $userId): string
    {
        return "purchase_limit:user:{$productId}:{$userId}";
    }

    protected function getIpKey(int $productId, string $ip): string
    {
        return "purchase_limit:ip:{$productId}:{$ip}";
    }

    protected function getDeviceKey(int $productId, string $deviceId): string
    {
        return "purchase_limit:device:{$productId}:{$deviceId}";
    }

    public function checkLimit(int $productId, int $userId, string $ip, string $deviceId = ''): array
    {
        $product = ShopProduct::where('id', $productId)->find();
        if (!$product) {
            return ['success' => false, 'message' => '商品不存在'];
        }

        if ($product->limit_per_user > 0) {
            $userCount = $this->getUserPurchaseCount($productId, $userId);
            if ($userCount >= $product->limit_per_user) {
                return [
                    'success' => false,
                    'message' => "每用户限购{$product->limit_per_user}份，您已达到限购数量",
                ];
            }
        }

        if ($product->limit_per_ip > 0) {
            $ipCount = $this->getIpPurchaseCount($productId, $ip);
            if ($ipCount >= $product->limit_per_ip) {
                return [
                    'success' => false,
                    'message' => "每IP限购{$product->limit_per_ip}份，您已达到限购数量",
                ];
            }
        }

        if ($product->limit_per_device > 0 && !empty($deviceId)) {
            $deviceCount = $this->getDevicePurchaseCount($productId, $deviceId);
            if ($deviceCount >= $product->limit_per_device) {
                return [
                    'success' => false,
                    'message' => "每设备限购{$product->limit_per_device}份，您已达到限购数量",
                ];
            }
        }

        return ['success' => true, 'message' => '校验通过'];
    }

    public function getUserPurchaseCount(int $productId, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return Order::where('product_id', $productId)
            ->where('user_id', $userId)
            ->whereIn('pay_status', [Order::STATUS_PAID, Order::STATUS_PENDING])
            ->count();
    }

    public function getIpPurchaseCount(int $productId, string $ip): int
    {
        if (empty($ip)) {
            return 0;
        }

        return Order::where('product_id', $productId)
            ->where('buyer_ip', $ip)
            ->whereIn('pay_status', [Order::STATUS_PAID, Order::STATUS_PENDING])
            ->count();
    }

    public function getDevicePurchaseCount(int $productId, string $deviceId): int
    {
        if (empty($deviceId)) {
            return 0;
        }

        return Order::where('product_id', $productId)
            ->where('device_id', $deviceId)
            ->whereIn('pay_status', [Order::STATUS_PAID, Order::STATUS_PENDING])
            ->count();
    }

    public function incrementCount(int $productId, int $userId, string $ip, string $deviceId = ''): void
    {
    }
}
