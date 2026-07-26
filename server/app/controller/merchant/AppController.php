<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\library\AesEncrypt;
use app\library\Bcrypt;
use app\model\App;
use app\model\Card;
use app\model\ApiLog;
use app\model\OperationLog;
use app\model\User;
use app\service\AppService;
use think\Request;

class AppController extends BaseController
{
    public function index(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $keyword = $request->param('keyword', '');
        $status = $request->param('status', '');

        $query = App::where('merchant_id', $merchantId);

        $appIds = $request->app_ids ?? null;
        if (!empty($appIds) && is_array($appIds)) {
            $query->whereIn('id', $appIds);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', '%' . $keyword . '%')
                    ->whereOr('app_key', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        $list = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        $totalApp = App::where('merchant_id', $merchantId)->count();
        $enabledApp = App::where('merchant_id', $merchantId)->where('status', 1)->count();
        $disabledApp = $totalApp - $enabledApp;

        $data = [
            'list' => $list->items(),
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
            'stats' => [
                'total' => $totalApp,
                'enabled' => $enabledApp,
                'disabled' => $disabledApp,
            ],
        ];

        return success($data, '获取成功');
    }

    public function read(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $app = App::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        $cardStats = [
            'total' => Card::where('app_id', $id)->where('merchant_id', $merchantId)->count(),
            'used' => Card::where('app_id', $id)->where('merchant_id', $merchantId)->where('status', '>=', 2)->count(),
            'unused' => Card::where('app_id', $id)->where('merchant_id', $merchantId)->where('status', 1)->count(),
            'activated' => Card::where('app_id', $id)->where('merchant_id', $merchantId)->where('status', 2)->count(),
            'expired' => Card::where('app_id', $id)->where('merchant_id', $merchantId)->where('status', 3)->count(),
            'banned' => Card::where('app_id', $id)->where('merchant_id', $merchantId)->where('status', 4)->count(),
        ];

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $weekStart = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $monthStart = date('Y-m-01 00:00:00');

        $apiStats = [
            'today' => ApiLog::where('app_id', $id)
                ->where('created_at', '>=', $todayStart)
                ->where('created_at', '<=', $todayEnd)
                ->count(),
            'week' => ApiLog::where('app_id', $id)
                ->where('created_at', '>=', $weekStart)
                ->count(),
            'month' => ApiLog::where('app_id', $id)
                ->where('created_at', '>=', $monthStart)
                ->count(),
        ];

        $data = $app->toArray();
        $data['card_stats'] = $cardStats;
        $data['api_stats'] = $apiStats;

        return success($data, '获取成功');
    }

    public function save(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $data = $request->param();

        $this->validate($data, [
            'name' => 'require|max:100',
            'icon' => 'max:255',
            'version' => 'max:20',
            'description' => 'max:500',
            'bind_limit' => 'integer|>=:1',
        ], [
            'name.require' => '应用名称不能为空',
            'name.max' => '应用名称不能超过100个字符',
            'icon.max' => '图标地址不能超过255个字符',
            'version.max' => '版本号不能超过20个字符',
            'description.max' => '描述不能超过500个字符',
            'bind_limit.integer' => '绑定上限必须是整数',
            'bind_limit.>=' => '绑定上限不能小于1',
        ]);

        $appKey = AppService::generateAppKey();
        $appSecret = AppService::generateAppSecret();

        $app = new App();
        $app->merchant_id = $merchantId;
        $app->name = $data['name'];
        $app->icon = $data['icon'] ?? '';
        $app->version = $data['version'] ?? '';
        $app->description = $data['description'] ?? '';
        $app->app_key = $appKey;
        $app->app_secret_hash = Bcrypt::hash($appSecret);
        $app->app_secret_encrypted = AesEncrypt::encrypt($appSecret);
        $app->bind_limit = $data['bind_limit'] ?? 1;
        $app->ip_whitelist = $data['ip_whitelist'] ?? '';
        $app->status = 1;
        $app->save();

        $this->logOperation($request, 'create_app', 'app', $app->id, [
            'name' => $app->name,
            'app_key' => $appKey,
        ]);

        $result = $app->toArray();
        $result['app_secret'] = $appSecret;

        return success($result, '创建成功');
    }

    public function update(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $app = App::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        $data = $request->param();

        $this->validate($data, [
            'name' => 'max:100',
            'icon' => 'max:255',
            'version' => 'max:20',
            'description' => 'max:500',
            'bind_limit' => 'integer|>=:1',
        ]);

        if (isset($data['name'])) {
            $app->name = $data['name'];
        }
        if (isset($data['icon'])) {
            $app->icon = $data['icon'];
        }
        if (isset($data['version'])) {
            $app->version = $data['version'];
        }
        if (isset($data['description'])) {
            $app->description = $data['description'];
        }
        if (isset($data['bind_limit'])) {
            $app->bind_limit = $data['bind_limit'];
        }
        if (isset($data['ip_whitelist'])) {
            $app->ip_whitelist = $data['ip_whitelist'];
        }

        $app->save();

        $this->logOperation($request, 'update_app', 'app', $app->id, $data);

        return success($app, '更新成功');
    }

    public function updateStatus(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $app = App::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        $status = $request->param('status', 0);
        if (!in_array($status, [0, 1])) {
            return error('状态值无效', 400);
        }

        $app->status = $status;
        $app->save();

        $this->logOperation($request, $status == 1 ? 'enable_app' : 'disable_app', 'app', $app->id, [
            'name' => $app->name,
            'status' => $status,
        ]);

        return success($app, $status == 1 ? '启用成功' : '停用成功');
    }

    public function resetSecret(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $app = App::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        $password = $request->param('password', '');
        if (empty($password)) {
            return error('请输入登录密码进行二次验证', 400);
        }

        $user = User::find($userId);
        if (!$user || !Bcrypt::verify($password, $user->password_hash)) {
            return error('密码错误', 400);
        }

        $newSecret = AppService::generateAppSecret();
        $app->app_secret_hash = Bcrypt::hash($newSecret);
        $app->app_secret_encrypted = AesEncrypt::encrypt($newSecret);
        $app->save();

        $this->logOperation($request, 'reset_app_secret', 'app', $app->id, [
            'name' => $app->name,
        ]);

        return success([
            'app_secret' => $newSecret,
        ], '重置成功');
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
