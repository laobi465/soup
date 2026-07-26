<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\User;
use app\model\Merchant;
use app\model\SubRole;
use app\model\App;
use app\library\Bcrypt;
use think\Request;
use think\facade\Db;

class SubAccountController extends BaseController
{
    public function index(Request $request)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $keyword = $request->param('keyword', '');
        $status = $request->param('status', '');
        $roleId = $request->param('role_id', '');

        $query = User::where('role_type', 4)
            ->where('merchant_id', $merchantId);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('username', '%' . $keyword . '%')
                    ->whereOr('real_name', 'like', '%' . $keyword . '%')
                    ->whereOr('email', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        if ($roleId) {
            $query->where('sub_role_id', intval($roleId));
        }

        $list = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        $items = [];
        foreach ($list->items() as $user) {
            $item = $user->toArray();
            $item['role_name'] = $user->sub_role_id ? ($this->getRoleName($user->sub_role_id) ?: '') : '';
            $item['app_count'] = is_array($user->app_ids) ? count($user->app_ids) : 0;
            unset($item['password_hash']);
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

    public function save(Request $request)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $username = $request->param('username', '');
        $password = $request->param('password', '');
        $realName = $request->param('real_name', '');
        $email = $request->param('email', '');
        $roleId = $request->param('role_id', 0);
        $appIds = $request->param('app_ids/a', []);

        if (!$username || !$password || !$email) {
            return error('请填写完整信息', 400);
        }

        if (strlen($username) < 4 || strlen($username) > 20) {
            return error('用户名长度需在4-20个字符之间', 400);
        }

        if (strlen($password) < 6 || strlen($password) > 32) {
            return error('密码长度需在6-32个字符之间', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return error('邮箱格式不正确', 400);
        }

        $exists = User::where('username', $username)->find();
        if ($exists) {
            return error('用户名已存在', 400);
        }

        $exists = User::where('email', $email)->find();
        if ($exists) {
            return error('邮箱已被使用', 400);
        }

        $subAccountCount = User::where('role_type', 4)
            ->where('merchant_id', $merchantId)
            ->count();

        $merchant = Merchant::find($merchantId);
        $package = $merchant->package;
        $subAccountLimit = $package->sub_account_limit ?? 0;
        if ($subAccountLimit > 0 && $subAccountCount >= $subAccountLimit) {
            return error('子账号数量已达套餐上限', 400);
        }

        if ($roleId) {
            $role = SubRole::where('id', $roleId)
                ->where('merchant_id', $merchantId)
                ->find();
            if (!$role) {
                return error('角色不存在', 400);
            }
        }

        if (!empty($appIds)) {
            $appCount = App::where('merchant_id', $merchantId)
                ->whereIn('id', $appIds)
                ->count();
            if ($appCount != count($appIds)) {
                return error('存在无效的应用ID', 400);
            }
        }

        Db::startTrans();
        try {
            $passwordHash = Bcrypt::hash($password);

            $user = new User();
            $user->username = $username;
            $user->password_hash = $passwordHash;
            $user->email = $email;
            $user->real_name = $realName;
            $user->role_type = 4;
            $user->merchant_id = $merchantId;
            $user->parent_id = $merchant->user_id;
            $user->sub_role_id = $roleId ?: null;
            $user->app_ids = !empty($appIds) ? $appIds : null;
            $user->status = 1;
            $user->login_fail_count = 0;
            $user->created_at = date('Y-m-d H:i:s');
            $user->updated_at = date('Y-m-d H:i:s');
            $user->save();

            Db::commit();

            return success([
                'id' => $user->id,
                'username' => $user->username,
            ], '创建成功');
        } catch (\Exception $e) {
            Db::rollback();
            return error('创建失败：' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $user = User::where('id', $id)
            ->where('role_type', 4)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$user) {
            return error('子账号不存在', 404);
        }

        $realName = $request->param('real_name', '');
        $email = $request->param('email', '');
        $roleId = $request->param('role_id', 0);
        $appIds = $request->param('app_ids/a', []);

        if (!$email) {
            return error('邮箱不能为空', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return error('邮箱格式不正确', 400);
        }

        if ($email != $user->email) {
            $exists = User::where('email', $email)->where('id', '<>', $id)->find();
            if ($exists) {
                return error('邮箱已被使用', 400);
            }
        }

        if ($roleId) {
            $role = SubRole::where('id', $roleId)
                ->where('merchant_id', $merchantId)
                ->find();
            if (!$role) {
                return error('角色不存在', 400);
            }
        }

        if (!empty($appIds)) {
            $appCount = App::where('merchant_id', $merchantId)
                ->whereIn('id', $appIds)
                ->count();
            if ($appCount != count($appIds)) {
                return error('存在无效的应用ID', 400);
            }
        }

        $user->real_name = $realName;
        $user->email = $email;
        $user->sub_role_id = $roleId ?: null;
        $user->app_ids = !empty($appIds) ? $appIds : null;
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        return success(null, '更新成功');
    }

    public function updateStatus(Request $request, $id)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $user = User::where('id', $id)
            ->where('role_type', 4)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$user) {
            return error('子账号不存在', 404);
        }

        $status = $request->param('status', 1);
        if (!in_array($status, [0, 1])) {
            return error('状态值无效', 400);
        }

        $user->status = $status;
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        return success(null, $status == 1 ? '启用成功' : '禁用成功');
    }

    public function resetPassword(Request $request, $id)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $user = User::where('id', $id)
            ->where('role_type', 4)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$user) {
            return error('子账号不存在', 404);
        }

        $password = $request->param('password', '');
        if (!$password) {
            return error('新密码不能为空', 400);
        }

        if (strlen($password) < 6 || strlen($password) > 32) {
            return error('密码长度需在6-32个字符之间', 400);
        }

        $passwordHash = Bcrypt::hash($password);
        $user->password_hash = $passwordHash;
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        return success(null, '重置密码成功');
    }

    public function delete(Request $request, $id)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $user = User::where('id', $id)
            ->where('role_type', 4)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$user) {
            return error('子账号不存在', 404);
        }

        $user->delete();

        return success(null, '删除成功');
    }

    protected function getRoleName(int $roleId): string
    {
        $role = SubRole::find($roleId);
        return $role ? $role->name : '';
    }
}
