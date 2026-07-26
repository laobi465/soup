<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Merchant;
use app\model\User;
use app\model\Package;
use app\model\OperationLog;
use app\library\Bcrypt;
use think\Request;

class MerchantController extends BaseController
{
    public function index(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $keyword = $request->param('keyword', '');
        $status = $request->param('status', '');
        $packageId = $request->param('package_id', '');

        $query = Merchant::with(['user', 'package'])
            ->order('id', 'desc');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('merchant_name', '%' . $keyword . '%')
                    ->whereOr('merchant_no', 'like', '%' . $keyword . '%');
            });
            $query->whereHas('user', function ($q) use ($keyword) {
                $q->whereLike('email', '%' . $keyword . '%');
            }, 'or');
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        if ($packageId !== '') {
            $query->where('package_id', intval($packageId));
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $merchant) {
            $item = $merchant->toArray();
            $item['user'] = $merchant->user ? [
                'id' => $merchant->user->id,
                'username' => $merchant->user->username,
                'email' => $merchant->user->email,
                'phone' => $merchant->user->phone,
                'avatar' => $merchant->user->avatar,
                'status' => $merchant->user->status,
            ] : null;
            $items[] = $item;
        }

        $data = [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];

        return success($data, '获取成功');
    }

    public function read($id)
    {
        $merchant = Merchant::with(['user', 'package'])->find($id);
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $data = $merchant->toArray();
        $data['user'] = $merchant->user ? [
            'id' => $merchant->user->id,
            'username' => $merchant->user->username,
            'email' => $merchant->user->email,
            'phone' => $merchant->user->phone,
            'avatar' => $merchant->user->avatar,
            'status' => $merchant->user->status,
            'created_at' => $merchant->user->created_at,
            'last_login_at' => $merchant->user->last_login_at,
            'last_login_ip' => $merchant->user->last_login_ip,
        ] : null;

        $data['remaining_apps'] = $merchant->remaining_apps;
        $data['remaining_cards'] = $merchant->remaining_cards;
        $data['is_package_expired'] = $merchant->isPackageExpired();

        $operationLogs = OperationLog::where('target_type', 'merchant')
            ->where('target_id', $id)
            ->order('id', 'desc')
            ->limit(20)
            ->select();

        $data['operation_logs'] = $operationLogs;

        return success($data, '获取成功');
    }

    public function updateStatus(Request $request, $id)
    {
        $merchant = Merchant::find($id);
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $status = $request->param('status', 0);
        if (!in_array($status, [0, 1])) {
            return error('状态值无效', 400);
        }

        $oldStatus = $merchant->status;
        $merchant->status = $status;
        $merchant->save();

        $user = User::find($merchant->user_id);
        if ($user) {
            $user->status = $status;
            $user->save();
        }

        $this->logOperation($request, $status == 1 ? 'enable_merchant' : 'disable_merchant', 'merchant', $id, [
            'old_status' => $oldStatus,
            'new_status' => $status,
        ]);

        return success(null, $status == 1 ? '商户已启用' : '商户已禁用');
    }

    public function resetPassword(Request $request, $id)
    {
        $merchant = Merchant::find($id);
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $newPassword = $request->param('password', '');
        if (empty($newPassword)) {
            $newPassword = $this->generateRandomPassword();
        }

        if (strlen($newPassword) < 6) {
            return error('密码长度不能少于6位', 400);
        }

        $user = User::find($merchant->user_id);
        if (!$user) {
            return error('用户不存在', 404);
        }

        $user->password_hash = Bcrypt::hash($newPassword);
        $user->save();

        $this->logOperation($request, 'reset_merchant_password', 'merchant', $id);

        return success([
            'password' => $newPassword,
        ], '密码重置成功');
    }

    public function adjustQuota(Request $request, $id)
    {
        $merchant = Merchant::find($id);
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $type = $request->param('type', '');
        $amount = $request->param('amount', 0);
        $remark = $request->param('remark', '');

        if (!in_array($type, ['app', 'card'])) {
            return error('调整类型无效', 400);
        }

        if (!is_numeric($amount) || intval($amount) == 0) {
            return error('调整数量无效', 400);
        }

        $oldValue = $type == 'app' ? $merchant->app_quota : $merchant->card_quota;
        $newValue = $oldValue + intval($amount);

        if ($newValue < 0) {
            return error('调整后额度不能小于0', 400);
        }

        if ($type == 'app') {
            $merchant->app_quota = $newValue;
        } else {
            $merchant->card_quota = $newValue;
        }
        $merchant->save();

        $this->logOperation($request, 'adjust_merchant_quota', 'merchant', $id, [
            'type' => $type,
            'old_value' => $oldValue,
            'amount' => $amount,
            'new_value' => $newValue,
            'remark' => $remark,
        ]);

        return success([
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ], '额度调整成功');
    }

    public function changePackage(Request $request, $id)
    {
        $merchant = Merchant::find($id);
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $packageId = $request->param('package_id', 0);
        $duration = $request->param('duration', 'month');

        if (!$packageId) {
            return error('请选择套餐', 400);
        }

        $package = Package::find($packageId);
        if (!$package || $package->status != 1) {
            return error('套餐不存在或已禁用', 404);
        }

        $oldPackageId = $merchant->package_id;
        $oldExpire = $merchant->package_expire;

        $days = match ($duration) {
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 30,
        };

        $now = time();
        if ($merchant->package_expire && strtotime($merchant->package_expire) > $now) {
            $newExpire = date('Y-m-d H:i:s', strtotime($merchant->package_expire) + $days * 86400);
        } else {
            $newExpire = date('Y-m-d H:i:s', $now + $days * 86400);
        }

        $merchant->package_id = $packageId;
        $merchant->package_expire = $newExpire;

        if ($package->app_limit > 0) {
            $merchant->app_quota = $package->app_limit;
        }
        if ($package->card_limit > 0) {
            $merchant->card_quota = $package->card_limit;
        }

        $merchant->save();

        $this->logOperation($request, 'change_merchant_package', 'merchant', $id, [
            'old_package_id' => $oldPackageId,
            'new_package_id' => $packageId,
            'old_expire' => $oldExpire,
            'new_expire' => $newExpire,
            'duration' => $duration,
        ]);

        return success([
            'package_expire' => $newExpire,
        ], '套餐变更成功');
    }

    protected function generateRandomPassword($length = 8): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password;
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
