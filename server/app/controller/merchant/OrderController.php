<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\Order;
use app\model\Merchant;
use app\model\ShopProduct;
use app\service\PaymentService;
use think\Request;

class OrderController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $type = $request->param('type', '');
        $status = $request->param('status', '');
        $keyword = $request->param('keyword', '');
        $startDate = $request->param('start_date', '');
        $endDate = $request->param('end_date', '');

        $query = Order::where('merchant_id', $merchant->id)->order('id', 'desc');

        $appIds = $request->app_ids ?? null;
        if (!empty($appIds) && is_array($appIds)) {
            $query->whereIn('app_id', $appIds);
        }

        if ($type !== '') {
            $query->where('type', intval($type));
        }

        if ($status !== '') {
            $query->where('pay_status', intval($status));
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', '%' . $keyword . '%');
            });
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'order_no' => $item->order_no,
                'type' => $item->type,
                'type_text' => $item->type_text,
                'product_id' => $item->product_id,
                'amount' => $item->amount,
                'pay_channel' => $item->pay_channel,
                'pay_channel_text' => $item->pay_channel_text,
                'pay_status' => $item->pay_status,
                'status_text' => $item->status_text,
                'pay_time' => $item->pay_time,
                'commission_amount' => $item->commission_amount,
                'created_at' => $item->created_at,
            ];
        }

        $data = [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];

        return success($data, '获取成功');
    }

    public function read(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $order = Order::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->with(['product', 'card'])
            ->find();

        if (!$order) {
            return error('订单不存在', 404);
        }

        return success([
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => $order->type,
            'type_text' => $order->type_text,
            'product_id' => $order->product_id,
            'product_name' => $order->product ? $order->product->name : '',
            'card_id' => $order->card_id,
            'card_no' => $order->card ? $order->card->card_no : '',
            'amount' => $order->amount,
            'pay_channel' => $order->pay_channel,
            'pay_channel_text' => $order->pay_channel_text,
            'pay_status' => $order->pay_status,
            'status_text' => $order->status_text,
            'pay_time' => $order->pay_time,
            'expire_time' => $order->expire_time,
            'commission_amount' => $order->commission_amount,
            'email' => $order->email ?? '',
            'created_at' => $order->created_at,
        ], '获取成功');
    }

    public function recharge(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $amount = $request->param('amount', 0);
        $payChannel = $request->param('pay_channel', 'caihong');
        $payType = $request->param('pay_type', 'alipay');

        if (!is_numeric($amount) || floatval($amount) <= 0) {
            return error('充值金额无效', 400);
        }

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentOrder(
            Order::TYPE_RECHARGE,
            $userId,
            $merchant->id,
            0,
            floatval($amount),
            $payChannel,
            [
                'product_name' => '余额充值',
                'pay_type' => $payType,
            ]
        );

        if ($result['success']) {
            return success($result, '充值订单创建成功');
        } else {
            return error($result['message'] ?? '创建失败', 500);
        }
    }

    public function package(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $packageId = intval($request->param('package_id', 0));
        $payChannel = $request->param('pay_channel', 'balance');
        $duration = $request->param('duration', 'month');

        if ($packageId <= 0) {
            return error('请选择套餐', 400);
        }

        $package = \app\model\Package::where('id', $packageId)->find();
        if (!$package) {
            return error('套餐不存在', 404);
        }

        $amountMap = [
            'month' => $package->price_month,
            'quarter' => $package->price_quarter,
            'year' => $package->price_year,
        ];
        $amount = $amountMap[$duration] ?? $package->price_month;

        $paymentService = new PaymentService();
        $result = $paymentService->createPaymentOrder(
            Order::TYPE_PACKAGE,
            $userId,
            $merchant->id,
            $packageId,
            floatval($amount),
            $payChannel,
            [
                'product_name' => $package->name . ' - ' . $this->getDurationText($duration),
                'duration' => $duration,
            ]
        );

        if ($result['success']) {
            return success($result, '订单创建成功');
        } else {
            return error($result['message'] ?? '创建失败', 500);
        }
    }

    protected function getDurationText(string $duration): string
    {
        $map = [
            'month' => '月付',
            'quarter' => '季付',
            'year' => '年付',
        ];
        return $map[$duration] ?? $duration;
    }

    public function refund(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $orderId = intval($request->param('order_id', 0));
        $reason = $request->param('reason', '');

        if ($orderId <= 0) {
            return error('订单ID无效', 400);
        }

        $order = Order::where('id', $orderId)->where('merchant_id', $merchant->id)->find();
        if (!$order) {
            return error('订单不存在', 404);
        }

        if (!$order->isPaid()) {
            return error('订单未支付，不能申请退款', 400);
        }

        $paymentService = new PaymentService();
        $result = $paymentService->refundOrder($orderId, $reason);

        if ($result['success']) {
            $paymentService->logOperation($userId, 'order_refund', 'order', $orderId, ['reason' => $reason]);
            return success(null, '退款申请已提交');
        } else {
            return error($result['message'] ?? '退款失败', 500);
        }
    }
}
