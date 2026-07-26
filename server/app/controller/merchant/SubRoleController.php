<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\SubRole;
use think\Request;

class SubRoleController extends BaseController
{
    public function index(Request $request)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $query = SubRole::where('merchant_id', $merchantId)->order('sort', 'asc');

        $list = $query->select();

        $items = [];
        foreach ($list as $role) {
            $item = $role->toArray();
            $items[] = $item;
        }

        return success($items, '获取成功');
    }

    public function save(Request $request)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $name = $request->param('name', '');
        $description = $request->param('description', '');
        $permissions = $request->param('permissions/a', []);
        $sort = $request->param('sort', 0);

        if (!$name) {
            return error('角色名称不能为空', 400);
        }

        if (strlen($name) > 50) {
            return error('角色名称长度不能超过50个字符', 400);
        }

        $exists = SubRole::where('merchant_id', $merchantId)
            ->where('name', $name)
            ->find();
        if ($exists) {
            return error('角色名称已存在', 400);
        }

        $role = new SubRole();
        $role->merchant_id = $merchantId;
        $role->name = $name;
        $role->description = $description;
        $role->permissions = !empty($permissions) ? $permissions : null;
        $role->sort = intval($sort);
        $role->status = 1;
        $role->save();

        return success($role, '创建成功');
    }

    public function update(Request $request, $id)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $role = SubRole::where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$role) {
            return error('角色不存在', 404);
        }

        $name = $request->param('name', '');
        $description = $request->param('description', '');
        $permissions = $request->param('permissions/a', []);
        $sort = $request->param('sort', 0);
        $status = $request->param('status', '');

        if (!$name) {
            return error('角色名称不能为空', 400);
        }

        if (strlen($name) > 50) {
            return error('角色名称长度不能超过50个字符', 400);
        }

        if ($name != $role->name) {
            $exists = SubRole::where('merchant_id', $merchantId)
                ->where('name', $name)
                ->where('id', '<>', $id)
                ->find();
            if ($exists) {
                return error('角色名称已存在', 400);
            }
        }

        $role->name = $name;
        $role->description = $description;
        $role->permissions = !empty($permissions) ? $permissions : null;
        $role->sort = intval($sort);
        if ($status !== '') {
            $role->status = intval($status);
        }
        $role->save();

        return success($role, '更新成功');
    }

    public function delete(Request $request, $id)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $role = SubRole::where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$role) {
            return error('角色不存在', 404);
        }

        $usedCount = \app\model\User::where('sub_role_id', $id)
            ->where('role_type', 4)
            ->count();
        if ($usedCount > 0) {
            return error('该角色下存在子账号，无法删除', 400);
        }

        $role->delete();

        return success(null, '删除成功');
    }
}
