<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Package;
use app\model\OperationLog;
use think\Request;

class PackageController extends BaseController
{
    public function index(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $name = $request->param('name', '');
        $status = $request->param('status', '');

        $query = Package::order('sort', 'desc')
            ->order('id', 'desc');

        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $data = [
            'list' => $list->items(),
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];

        return success($data, '获取成功');
    }

    public function read($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return error('套餐不存在', 404);
        }

        return success($package, '获取成功');
    }

    public function save(Request $request)
    {
        $data = $request->param();

        $this->validate($data, [
            'name' => 'require|max:50',
            'price_month' => 'require|float|>=:0',
            'price_quarter' => 'require|float|>=:0',
            'price_year' => 'require|float|>=:0',
            'app_limit' => 'integer|>=:0',
            'card_limit' => 'integer|>=:0',
            'api_limit_day' => 'integer|>=:0',
            'online_limit' => 'integer|>=:0',
            'sub_account_limit' => 'integer|>=:0',
            'sort' => 'integer',
            'status' => 'in:0,1',
        ], [
            'name.require' => '套餐名称不能为空',
            'name.max' => '套餐名称不能超过50个字符',
            'price_month.require' => '月付价格不能为空',
            'price_month.float' => '月付价格格式不正确',
            'price_quarter.require' => '季付价格不能为空',
            'price_quarter.float' => '季付价格格式不正确',
            'price_year.require' => '年付价格不能为空',
            'price_year.float' => '年付价格格式不正确',
        ]);

        $exists = Package::where('name', $data['name'])->find();
        if ($exists) {
            return error('套餐名称已存在', 400);
        }

        $package = new Package();
        $package->name = $data['name'];
        $package->price_month = $data['price_month'] ?? 0;
        $package->price_quarter = $data['price_quarter'] ?? 0;
        $package->price_year = $data['price_year'] ?? 0;
        $package->app_limit = $data['app_limit'] ?? 0;
        $package->card_limit = $data['card_limit'] ?? 0;
        $package->api_limit_day = $data['api_limit_day'] ?? 0;
        $package->online_limit = $data['online_limit'] ?? 0;
        $package->sub_account_limit = $data['sub_account_limit'] ?? 0;
        $package->features = isset($data['features']) ? json_decode($data['features'], true) : null;
        $package->sort = $data['sort'] ?? 0;
        $package->status = $data['status'] ?? 1;
        $package->save();

        $this->logOperation($request, 'create_package', 'package', $package->id, $data);

        return success($package, '创建成功');
    }

    public function update(Request $request, $id)
    {
        $package = Package::find($id);
        if (!$package) {
            return error('套餐不存在', 404);
        }

        $data = $request->param();

        $this->validate($data, [
            'name' => 'max:50',
            'price_month' => 'float|>=:0',
            'price_quarter' => 'float|>=:0',
            'price_year' => 'float|>=:0',
            'app_limit' => 'integer|>=:0',
            'card_limit' => 'integer|>=:0',
            'api_limit_day' => 'integer|>=:0',
            'online_limit' => 'integer|>=:0',
            'sub_account_limit' => 'integer|>=:0',
            'sort' => 'integer',
            'status' => 'in:0,1',
        ]);

        if (isset($data['name']) && $data['name'] !== $package->name) {
            $exists = Package::where('name', $data['name'])
                ->where('id', '<>', $id)
                ->find();
            if ($exists) {
                return error('套餐名称已存在', 400);
            }
            $package->name = $data['name'];
        }

        if (isset($data['price_month'])) {
            $package->price_month = $data['price_month'];
        }
        if (isset($data['price_quarter'])) {
            $package->price_quarter = $data['price_quarter'];
        }
        if (isset($data['price_year'])) {
            $package->price_year = $data['price_year'];
        }
        if (isset($data['app_limit'])) {
            $package->app_limit = $data['app_limit'];
        }
        if (isset($data['card_limit'])) {
            $package->card_limit = $data['card_limit'];
        }
        if (isset($data['api_limit_day'])) {
            $package->api_limit_day = $data['api_limit_day'];
        }
        if (isset($data['online_limit'])) {
            $package->online_limit = $data['online_limit'];
        }
        if (isset($data['sub_account_limit'])) {
            $package->sub_account_limit = $data['sub_account_limit'];
        }
        if (isset($data['features'])) {
            $package->features = is_string($data['features'])
                ? json_decode($data['features'], true)
                : $data['features'];
        }
        if (isset($data['sort'])) {
            $package->sort = $data['sort'];
        }
        if (isset($data['status'])) {
            $package->status = $data['status'];
        }

        $package->save();

        $this->logOperation($request, 'update_package', 'package', $package->id, $data);

        return success($package, '更新成功');
    }

    public function delete(Request $request, $id)
    {
        $package = Package::find($id);
        if (!$package) {
            return error('套餐不存在', 404);
        }

        $package->status = 0;
        $package->save();

        $this->logOperation($request, 'delete_package', 'package', $package->id);

        return success(null, '删除成功');
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
