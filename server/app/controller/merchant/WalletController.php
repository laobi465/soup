<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\Wallet;
use app\model\WalletTransaction;
use app\model\Merchant;
use app\model\OperationLog;
use think\Request;

class WalletController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 1)
            ->find();

        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $userId;
            $wallet->type = 1;
            $wallet->balance = 0;
            $wallet->frozen = 0;
            $wallet->save();
        }

        $merchant = Merchant::where('user_id', $userId)->find();

        $data = [
            'wallet' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'frozen' => $wallet->frozen,
                'available' => bcsub(strval($wallet->balance), strval($wallet->frozen), 2),
                'type' => $wallet->type,
                'type_text' => $wallet->type_text,
            ],
            'merchant_balance' => $merchant ? $merchant->balance : 0,
        ];

        return success($data, '获取成功');
    }

    public function transactions(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 1)
            ->find();

        if (!$wallet) {
            return success([
                'list' => [],
                'total' => 0,
                'page' => 1,
                'pageSize' => 10,
            ], '获取成功');
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $type = $request->param('type', '');

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->order('id', 'desc');

        if ($type !== '') {
            $query->where('type', intval($type));
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'type' => $item->type,
                'type_text' => $item->type_text,
                'amount' => $item->amount,
                'balance_after' => $item->balance_after,
                'related_order' => $item->related_order,
                'remark' => $item->remark,
                'settle_status' => $item->settle_status,
                'settle_status_text' => $item->settle_status_text,
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

    public function recharge(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $amount = $request->param('amount', 0);
        $payType = $request->param('pay_type', 'balance');

        if (!is_numeric($amount) || floatval($amount) <= 0) {
            return error('充值金额无效', 400);
        }

        $amount = floatval($amount);
        $orderNo = 'R' . date('YmdHis') . str_pad(strval($userId), 6, '0', STR_PAD_LEFT) . mt_rand(100, 999);

        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 1)
            ->find();

        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $userId;
            $wallet->type = 1;
            $wallet->balance = 0;
            $wallet->frozen = 0;
            $wallet->save();
        }

        $this->logOperation($request, 'wallet_recharge_order', 'wallet', $wallet->id, [
            'order_no' => $orderNo,
            'amount' => $amount,
            'pay_type' => $payType,
        ]);

        return success([
            'order_no' => $orderNo,
            'amount' => $amount,
            'pay_type' => $payType,
        ], '充值订单创建成功，请完成支付');
    }

    protected function logOperation(Request $request, string $action, string $targetType, int $targetId, array $data = [])
    {
        $userId = $request->user_id ?? 0;
        $log = new OperationLog();
        $log->user_id = $userId;
        $log->action = $action;
        $log->target_type = $targetType;
        $log->target_id = $targetId;
        $log->ip = $request->ip();
        $log->user_agent = $request->header('User-Agent', '');
        $log->request_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $log->save();
    }
}
