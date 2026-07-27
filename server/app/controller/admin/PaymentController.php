<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Order;
use app\service\PaymentService;
use app\library\AesEncrypt;
use think\Request;
use think\facade\Db;

class PaymentController extends BaseController
{
    public function getConfig(Request $request)
    {
        $config = $this->getPaymentConfigFromDb();
        return success($config, '获取成功');
    }

    public function updateConfig(Request $request)
    {
        $data = $request->param();

        // 输入校验 (I7)
        $apiUrl = $data['caihong_api_url'] ?? '';
        $pid = $data['caihong_pid'] ?? '';
        $key = $data['caihong_key'] ?? '';

        // api_url 必须是 https URL
        if (!empty($apiUrl)) {
            if (!filter_var($apiUrl, FILTER_VALIDATE_URL) || strpos($apiUrl, 'https://') !== 0) {
                return error('api_url 必须是有效的 https URL');
            }
        }

        // pid 必须是整数
        if (!empty($pid) && !ctype_digit(strval($pid))) {
            return error('pid 必须是整数');
        }

        $caihongConfig = [
            'enabled' => intval($data['caihong_enabled'] ?? 0),
            'api_url' => $apiUrl,
            'pid' => $pid,
            'key' => $key,
        ];

        $this->savePaymentConfigToDb($caihongConfig);

        $paymentService = new PaymentService();
        $paymentService->logOperation($request->user_id ?? 0, 'payment_config_update', 'payment', 0, $data);

        return success(null, '配置更新成功');
    }

    protected function getPaymentConfigFromDb(): array
    {
        $config = [];

        $rows = Db::name('system_configs')
            ->where('config_key', 'like', 'payment_%')
            ->select();

        $caihong = [
            'enabled' => 0,
            'api_url' => '',
            'pid' => '',
            'key' => '',
        ];

        foreach ($rows as $row) {
            $key = $row['config_key'];
            $value = $row['config_value'];

            if ($key === 'payment_caihong_enabled') {
                $caihong['enabled'] = intval($value);
            } elseif ($key === 'payment_caihong_api_url') {
                $caihong['api_url'] = $value;
            } elseif ($key === 'payment_caihong_pid') {
                $caihong['pid'] = $value;
            } elseif ($key === 'payment_caihong_key') {
                // 读取时解密 (I7)
                $decrypted = AesEncrypt::decrypt($value);
                $caihong['key'] = ($decrypted !== false) ? $decrypted : $value;
            }
        }

        $config['caihong'] = $caihong;
        return $config;
    }

    protected function savePaymentConfigToDb(array $caihongConfig): void
    {
        // key 加密落库 (I7), 与 app_secret 安全实践一致
        $keyValue = $caihongConfig['key'] ?? '';
        if (!empty($keyValue)) {
            $keyValue = AesEncrypt::encrypt($keyValue);
        }

        $items = [
            'payment_caihong_enabled' => strval($caihongConfig['enabled'] ?? 0),
            'payment_caihong_api_url' => $caihongConfig['api_url'] ?? '',
            'payment_caihong_pid' => $caihongConfig['pid'] ?? '',
            'payment_caihong_key' => $keyValue,
        ];

        foreach ($items as $key => $value) {
            $exists = Db::name('system_configs')->where('config_key', $key)->find();
            if ($exists) {
                Db::name('system_configs')->where('config_key', $key)->update([
                    'config_value' => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                Db::name('system_configs')->insert([
                    'config_key' => $key,
                    'config_value' => $value,
                    'config_type' => 'payment',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function orders(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $type = $request->param('type', '');
        $status = $request->param('status', '');
        $keyword = $request->param('keyword', '');
        $merchantId = $request->param('merchant_id', '');
        $startDate = $request->param('start_date', '');
        $endDate = $request->param('end_date', '');

        $query = Order::with(['merchant'])->order('id', 'desc');

        if ($type !== '') {
            $query->where('type', intval($type));
        }

        if ($status !== '') {
            $query->where('pay_status', intval($status));
        }

        if ($merchantId !== '') {
            $query->where('merchant_id', intval($merchantId));
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
                'merchant_id' => $item->merchant_id,
                'merchant_name' => $item->merchant ? $item->merchant->merchant_name : '',
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

    public function orderDetail(Request $request, $id)
    {
        $order = Order::where('id', $id)
            ->with(['merchant', 'product', 'card', 'user'])
            ->find();

        if (!$order) {
            return error('订单不存在', 404);
        }

        return success([
            'id' => $order->id,
            'order_no' => $order->order_no,
            'type' => $order->type,
            'type_text' => $order->type_text,
            'merchant_id' => $order->merchant_id,
            'merchant_name' => $order->merchant ? $order->merchant->merchant_name : '',
            'user_id' => $order->user_id,
            'username' => $order->user ? $order->user->username : '',
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
            'pay_trade_no' => $order->pay_trade_no ?? '',
            'expire_time' => $order->expire_time,
            'agent_id' => $order->agent_id,
            'commission_amount' => $order->commission_amount,
            'settle_status' => $order->settle_status,
            'email' => $order->email ?? '',
            'created_at' => $order->created_at,
        ], '获取成功');
    }

    public function refund(Request $request)
    {
        $orderId = intval($request->param('order_id', 0));
        $reason = $request->param('reason', '');

        if ($orderId <= 0) {
            return error('订单ID无效', 400);
        }

        $order = Order::where('id', $orderId)->find();
        if (!$order) {
            return error('订单不存在', 404);
        }

        $paymentService = new PaymentService();
        $result = $paymentService->refundOrder($orderId, $reason);

        if ($result['success']) {
            $paymentService->logOperation($request->user_id ?? 0, 'admin_order_refund', 'order', $orderId, ['reason' => $reason]);
            return success(null, '退款成功');
        } else {
            return error($result['message'] ?? '退款失败', 500);
        }
    }
}
