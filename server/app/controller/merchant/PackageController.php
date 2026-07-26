<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\Merchant;
use app\model\Package;
use app\model\OperationLog;
use think\Request;

class PackageController extends BaseController
{
    public function current(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::with(['package'])
            ->where('user_id', $userId)
            ->find();

        if (!$merchant) {
            return error('商户信息不存在', 404);
        }

        $data = [
            'merchant' => [
                'id' => $merchant->id,
                'package_id' => $merchant->package_id,
                'package_expire' => $merchant->package_expire,
                'app_quota' => $merchant->app_quota,
                'card_quota' => $merchant->card_quota,
                'card_used' => $merchant->card_used,
                'remaining_apps' => $merchant->remaining_apps,
                'remaining_cards' => $merchant->remaining_cards,
                'is_package_expired' => $merchant->isPackageExpired(),
            ],
            'package' => $merchant->package ? [
                'id' => $merchant->package->id,
                'name' => $merchant->package->name,
                'price_month' => $merchant->package->price_month,
                'price_quarter' => $merchant->package->price_quarter,
                'price_year' => $merchant->package->price_year,
                'app_limit' => $merchant->package->app_limit,
                'card_limit' => $merchant->package->card_limit,
                'api_limit_day' => $merchant->package->api_limit_day,
                'online_limit' => $merchant->package->online_limit,
                'sub_account_limit' => $merchant->package->sub_account_limit,
                'features' => $merchant->package->features,
                'status' => $merchant->package->status,
            ] : null,
        ];

        return success($data, '获取成功');
    }

    public function index(Request $request)
    {
        $packages = Package::where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'asc')
            ->select();

        return success($packages, '获取成功');
    }

    public function upgrade(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户信息不存在', 404);
        }

        $packageId = $request->param('package_id', 0);
        $duration = $request->param('duration', 'month');

        if (!$packageId) {
            return error('请选择套餐', 400);
        }

        if (!in_array($duration, ['month', 'quarter', 'year'])) {
            return error('时长类型无效', 400);
        }

        $package = Package::find($packageId);
        if (!$package || $package->status != 1) {
            return error('套餐不存在或已下架', 404);
        }

        $priceField = 'price_' . $duration;
        $amount = $package->$priceField;

        $orderNo = 'P' . date('YmdHis') . str_pad(strval($userId), 6, '0', STR_PAD_LEFT) . mt_rand(100, 999);

        $this->logOperation($request, 'package_upgrade_order', 'package', $packageId, [
            'order_no' => $orderNo,
            'package_id' => $packageId,
            'package_name' => $package->name,
            'duration' => $duration,
            'amount' => $amount,
        ]);

        return success([
            'order_no' => $orderNo,
            'package_id' => $packageId,
            'package_name' => $package->name,
            'duration' => $duration,
            'amount' => $amount,
            'pay_type' => 'balance',
        ], '订单创建成功，请完成支付');
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
