<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\App;
use app\model\Card;
use app\model\ApiLog;
use app\model\OperationLog;
use think\Request;

class AppController extends BaseController
{
    public function index(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $keyword = $request->param('keyword', '');
        $status = $request->param('status', '');
        $merchantId = $request->param('merchant_id', '');

        $query = App::with(['merchant']);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', '%' . $keyword . '%')
                    ->whereOr('app_key', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        if ($merchantId !== '') {
            $query->where('merchant_id', intval($merchantId));
        }

        $list = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        $items = [];
        foreach ($list->items() as $app) {
            $item = $app->toArray();
            $item['merchant_name'] = $app->merchant ? $app->merchant->merchant_name : '';
            unset($item['merchant']);
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
        $app = App::with(['merchant'])->find($id);
        if (!$app) {
            return error('应用不存在', 404);
        }

        $cardStats = [
            'total' => Card::where('app_id', $id)->count(),
            'used' => Card::where('app_id', $id)->where('status', '>=', 2)->count(),
            'unused' => Card::where('app_id', $id)->where('status', 1)->count(),
            'activated' => Card::where('app_id', $id)->where('status', 2)->count(),
            'expired' => Card::where('app_id', $id)->where('status', 3)->count(),
            'banned' => Card::where('app_id', $id)->where('status', 4)->count(),
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
        $data['merchant_name'] = $app->merchant ? $app->merchant->merchant_name : '';
        unset($data['merchant']);
        $data['card_stats'] = $cardStats;
        $data['api_stats'] = $apiStats;

        return success($data, '获取成功');
    }

    public function updateStatus(Request $request, $id)
    {
        $app = App::find($id);
        if (!$app) {
            return error('应用不存在', 404);
        }

        $status = $request->param('status', 0);
        if (!in_array($status, [0, 1])) {
            return error('状态值无效', 400);
        }

        $app->status = $status;
        $app->save();

        $this->logOperation($request, $status == 1 ? 'admin_enable_app' : 'admin_disable_app', 'app', $app->id, [
            'name' => $app->name,
            'status' => $status,
        ]);

        return success($app, $status == 1 ? '启用成功' : '停用成功');
    }

    public function delete(Request $request, $id)
    {
        $app = App::find($id);
        if (!$app) {
            return error('应用不存在', 404);
        }

        $app->status = 0;
        $app->save();

        $this->logOperation($request, 'admin_delete_app', 'app', $app->id, [
            'name' => $app->name,
        ]);

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
