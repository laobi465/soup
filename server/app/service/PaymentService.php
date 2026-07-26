<?php
declare (strict_types = 1);

namespace app\service;

use app\library\Random;
use app\library\payment\drivers\CaihongPay;
use app\library\payment\PaymentDriver;
use app\model\Order;
use app\model\Merchant;
use app\model\Wallet;
use app\model\WalletTransaction;
use app\model\OperationLog;
use think\facade\Config;
use think\facade\Log;
use think\facade\Db;

class PaymentService
{
    protected function getDriver(string $channel): ?PaymentDriver
    {
        $config = $this->getPaymentConfig();
        $channelConfig = $config[$channel] ?? null;

        if (!$channelConfig || empty($channelConfig['enabled'])) {
            return null;
        }

        switch ($channel) {
            case 'caihong':
                return new CaihongPay($channelConfig);
            default:
                return null;
        }
    }

    protected function getPaymentConfig(): array
    {
        return Config::get('payment', []);
    }

    public function generateOrderNo(string $prefix = 'P'): string
    {
        return $prefix . date('YmdHis') . Random::numeric(6);
    }

    public function createPaymentOrder(
        int $type,
        int $userId,
        int $merchantId,
        int $productId,
        float $amount,
        string $payChannel,
        array $extra = []
    ): array {
        $orderNo = $this->generateOrderNo();

        $order = new Order();
        $order->order_no = $orderNo;
        $order->type = $type;
        $order->user_id = $userId;
        $order->merchant_id = $merchantId;
        $order->product_id = $productId;
        $order->amount = $amount;
        $order->pay_channel = $payChannel;
        $order->pay_status = Order::STATUS_PENDING;
        $order->expire_time = date('Y-m-d H:i:s', time() + 600);
        $order->extra = json_encode($extra, JSON_UNESCAPED_UNICODE);

        if (!empty($extra['agent_id'])) {
            $order->agent_id = intval($extra['agent_id']);
        }

        if (!empty($extra['email'])) {
            $order->email = $extra['email'];
        }

        if (!empty($extra['buyer_ip'])) {
            $order->buyer_ip = $extra['buyer_ip'];
        }

        if (!empty($extra['device_id'])) {
            $order->device_id = $extra['device_id'];
        }

        $order->save();

        if ($payChannel === 'balance') {
            return $this->payByBalance($order);
        }

        $driver = $this->getDriver($payChannel);
        if (!$driver) {
            return [
                'success' => false,
                'message' => '支付渠道不可用',
            ];
        }

        $notifyUrl = request()->domain() . '/api/pay/notify/' . $payChannel;
        $returnUrl = $extra['return_url'] ?? (request()->domain() . '/pay/result');

        $payResult = $driver->createOrder([
            'order_no' => $orderNo,
            'amount' => $amount,
            'product_name' => $extra['product_name'] ?? '商品支付',
            'pay_type' => $extra['pay_type'] ?? 'alipay',
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'client_ip' => request()->ip(),
        ]);

        if ($payResult['success']) {
            $order->pay_trade_no = $payResult['trade_no'] ?? '';
            $order->save();
        }

        return array_merge($payResult, [
            'order_no' => $orderNo,
            'order_id' => $order->id,
        ]);
    }

    protected function payByBalance(Order $order): array
    {
        $userId = $order->user_id;

        Db::startTrans();
        try {
            $wallet = Wallet::where('user_id', $userId)->where('type', 1)->lock(true)->find();

            if (!$wallet || floatval($wallet->balance) < floatval($order->amount)) {
                Db::rollback();
                return [
                    'success' => false,
                    'message' => '余额不足',
                ];
            }

            $oldBalance = $wallet->balance;
            $wallet->balance = bcsub(strval($wallet->balance), strval($order->amount), 2);
            $wallet->save();

            $transaction = new WalletTransaction();
            $transaction->wallet_id = $wallet->id;
            $transaction->type = 2;
            $transaction->amount = $order->amount;
            $transaction->related_order = $order->order_no;
            $transaction->balance_after = $wallet->balance;
            $transaction->remark = '订单支付：' . $order->order_no;
            $transaction->save();

            $order->pay_status = Order::STATUS_PAID;
            $order->pay_time = date('Y-m-d H:i:s');
            $order->save();

            $this->processOrderPaid($order);

            Db::commit();

            return [
                'success' => true,
                'order_no' => $order->order_no,
                'order_id' => $order->id,
                'message' => '支付成功',
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('balance_pay_error', ['msg' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => '支付失败：' . $e->getMessage(),
            ];
        }
    }

    public function handleNotify(string $payChannel, array $data): bool
    {
        $driver = $this->getDriver($payChannel);
        if (!$driver) {
            Log::error('pay_notify_driver_not_found', ['channel' => $payChannel]);
            return false;
        }

        if (!$driver->verifyNotify($data)) {
            Log::error('pay_notify_verify_failed', ['data' => $data]);
            return false;
        }

        $orderNo = $data['out_trade_no'] ?? '';
        $tradeStatus = $data['trade_status'] ?? ($data['status'] ?? '');

        if (!$orderNo) {
            Log::error('pay_notify_no_order_no', ['data' => $data]);
            return false;
        }

        $isPaid = in_array($tradeStatus, ['TRADE_SUCCESS', 'success', 1]);
        if (!$isPaid) {
            Log::info('pay_notify_trade_not_success', ['order_no' => $orderNo, 'status' => $tradeStatus]);
            return false;
        }

        Db::startTrans();
        try {
            $affected = Order::where('order_no', $orderNo)
                ->where('pay_status', Order::STATUS_PENDING)
                ->update([
                    'pay_status' => Order::STATUS_PAID,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_trade_no' => $data['trade_no'] ?? '',
                ]);
            if (!$affected) {
                Db::rollback();
                Log::info('pay_notify_already_processed', ['order_no' => $orderNo]);
                return true;
            }

            $order = Order::where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                Db::rollback();
                Log::error('pay_notify_order_not_found', ['order_no' => $orderNo]);
                return false;
            }

            $merchant = Merchant::where('id', $order->merchant_id)->lock(true)->find();
            if ($merchant && $order->type == Order::TYPE_RECHARGE) {
                $merchant->balance = bcadd(strval($merchant->balance), strval($order->amount), 2);
                $merchant->save();
            }

            $this->processOrderPaid($order);

            Db::commit();

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('pay_notify_process_error', [
                'order_no' => $orderNo,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function processOrderPaid(Order $order): void
    {
        try {
            switch ($order->type) {
                case Order::TYPE_RECHARGE:
                    $this->processRecharge($order);
                    break;
                case Order::TYPE_PACKAGE:
                case Order::TYPE_RENEW:
                    $this->processPackage($order);
                    break;
                case Order::TYPE_SHOP:
                    $this->processShopOrder($order);
                    break;
            }

            if ($order->agent_id > 0) {
                try {
                    $commissionService = new CommissionService();
                    $commissionService->calculateCommission($order);
                } catch (\Exception $e) {
                    Log::error('calculate_commission_error', [
                        'order_id' => $order->id,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('process_order_paid_error', [
                'order_id' => $order->id,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function processRecharge(Order $order): void
    {
        $merchant = Merchant::where('id', $order->merchant_id)->find();
        if (!$merchant) {
            return;
        }

        $wallet = Wallet::where('user_id', $merchant->user_id)->where('type', 1)->find();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $merchant->user_id;
            $wallet->type = 1;
            $wallet->balance = 0;
            $wallet->frozen = 0;
        }

        $oldBalance = $wallet->balance;
        $wallet->balance = bcadd(strval($wallet->balance), strval($order->amount), 2);
        $wallet->save();

        $transaction = new WalletTransaction();
        $transaction->wallet_id = $wallet->id;
        $transaction->type = 1;
        $transaction->amount = $order->amount;
        $transaction->related_order = $order->order_no;
        $transaction->balance_after = $wallet->balance;
        $transaction->remark = '余额充值';
        $transaction->settle_status = 1;
        $transaction->save();
    }

    protected function processPackage(Order $order): void
    {
    }

    protected function processShopOrder(Order $order): void
    {
        try {
            $extra = json_decode($order->extra, true);
            $quantity = isset($extra['quantity']) ? intval($extra['quantity']) : 1;
            if ($quantity <= 0) {
                $quantity = 1;
            }
            $cardService = new CardDeliveryService();
            $cardService->deliverCard($order->id, $quantity);
        } catch (\Exception $e) {
            Log::error('deliver_card_error', [
                'order_id' => $order->id,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function closeExpiredOrders(): int
    {
        return Order::where('pay_status', Order::STATUS_PENDING)
            ->where('expire_time', '<', date('Y-m-d H:i:s'))
            ->limit(500)
            ->update(['pay_status' => Order::STATUS_CLOSED]);
    }

    public function refundOrder(int $orderId, string $reason = ''): array
    {
        $order = Order::where('id', $orderId)->find();
        if (!$order) {
            return ['success' => false, 'message' => '订单不存在'];
        }

        if (!$order->isPaid()) {
            return ['success' => false, 'message' => '订单未支付，不能退款'];
        }

        if ($order->isRefunded()) {
            return ['success' => false, 'message' => '订单已退款'];
        }

        Db::startTrans();
        try {
            $order->pay_status = Order::STATUS_REFUNDED;
            $order->refund_reason = $reason;
            $order->refund_time = date('Y-m-d H:i:s');
            $order->save();

            if ($order->type == Order::TYPE_RECHARGE) {
                $merchant = Merchant::where('id', $order->merchant_id)->find();
                if ($merchant) {
                    $merchant->balance = bcsub(strval($merchant->balance), strval($order->amount), 2);
                    $merchant->save();
                }
            }

            Db::commit();

            try {
                $commissionService = new CommissionService();
                $commissionService->handleRefund($order);
            } catch (\Exception $e) {
                Log::error('refund_commission_error', ['order_id' => $orderId, 'msg' => $e->getMessage()]);
            }

            return ['success' => true, 'message' => '退款成功'];
        } catch (\Exception $e) {
            Db::rollback();
            return ['success' => false, 'message' => '退款失败：' . $e->getMessage()];
        }
    }

    public function logOperation(int $userId, string $action, string $targetType, int $targetId, array $data = []): void
    {
        $log = new OperationLog();
        $log->user_id = $userId;
        $log->action = $action;
        $log->target_type = $targetType;
        $log->target_id = $targetId;
        $log->ip = request()->ip();
        $log->user_agent = request()->header('User-Agent', '');
        $log->request_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $log->save();
    }
}
