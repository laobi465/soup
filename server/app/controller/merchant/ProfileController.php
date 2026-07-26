<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\Merchant;
use app\model\User;
use app\library\Bcrypt;
use think\Request;

class ProfileController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::with(['package', 'user'])
            ->where('user_id', $userId)
            ->find();

        if (!$merchant) {
            return error('商户信息不存在', 404);
        }

        $data = [
            'merchant' => [
                'id' => $merchant->id,
                'merchant_no' => $merchant->merchant_no,
                'merchant_name' => $merchant->merchant_name,
                'package_id' => $merchant->package_id,
                'package_expire' => $merchant->package_expire,
                'balance' => $merchant->balance,
                'frozen_balance' => $merchant->frozen_balance,
                'app_quota' => $merchant->app_quota,
                'card_quota' => $merchant->card_quota,
                'card_used' => $merchant->card_used,
                'status' => $merchant->status,
                'status_text' => $merchant->status_text,
                'created_at' => $merchant->created_at,
                'remaining_apps' => $merchant->remaining_apps,
                'remaining_cards' => $merchant->remaining_cards,
                'is_package_expired' => $merchant->isPackageExpired(),
            ],
            'user' => [
                'id' => $merchant->user->id,
                'username' => $merchant->user->username,
                'email' => $merchant->user->email,
                'phone' => $merchant->user->phone,
                'avatar' => $merchant->user->avatar,
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
            ] : null,
        ];

        return success($data, '获取成功');
    }

    public function update(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户信息不存在', 404);
        }

        $data = $request->param();

        $this->validate($data, [
            'merchant_name' => 'max:100',
            'avatar' => 'max:255',
            'phone' => 'max:20',
        ], [
            'merchant_name.max' => '商户名称不能超过100个字符',
            'avatar.max' => '头像地址不能超过255个字符',
            'phone.max' => '手机号不能超过20个字符',
        ]);

        if (isset($data['merchant_name'])) {
            $merchant->merchant_name = $data['merchant_name'];
        }
        $merchant->save();

        $user = User::find($userId);
        if ($user) {
            if (isset($data['avatar'])) {
                $user->avatar = $data['avatar'];
            }
            if (isset($data['phone'])) {
                $user->phone = $data['phone'];
            }
            $user->save();
        }

        return success([
            'merchant_name' => $merchant->merchant_name,
            'avatar' => $user->avatar ?? null,
            'phone' => $user->phone ?? null,
        ], '更新成功');
    }

    public function changePassword(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $oldPassword = $request->param('old_password', '');
        $newPassword = $request->param('new_password', '');
        $confirmPassword = $request->param('confirm_password', '');

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            return error('请填写完整的密码信息', 400);
        }

        if (strlen($newPassword) < 6) {
            return error('新密码长度不能少于6位', 400);
        }

        if ($newPassword !== $confirmPassword) {
            return error('两次输入的新密码不一致', 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return error('用户不存在', 404);
        }

        if (!Bcrypt::verify($oldPassword, $user->password_hash)) {
            return error('原密码错误', 400);
        }

        $user->password_hash = Bcrypt::hash($newPassword);
        $user->save();

        return success(null, '密码修改成功');
    }
}
