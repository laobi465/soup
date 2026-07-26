<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\OperationLogService;
use app\model\OperationLog;
use app\model\ApiLog;
use think\Request;

class LogController extends BaseController
{
    public function operation(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $userId = $request->param('user_id', '');
        $action = $request->param('action', '');
        $targetType = $request->param('target_type', '');
        $ip = $request->param('ip', '');
        $startTime = $request->param('start_time', '');
        $endTime = $request->param('end_time', '');

        $filters = [
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'ip' => $ip,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        $data = OperationLogService::getList($filters, intval($page), intval($pageSize));
        return success($data, '获取成功');
    }

    public function login(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $userId = $request->param('user_id', '');
        $ip = $request->param('ip', '');
        $startTime = $request->param('start_time', '');
        $endTime = $request->param('end_time', '');

        $query = OperationLog::with(['user'])
            ->whereLike('action', 'login_%')
            ->order('id', 'desc');

        if ($userId) {
            $query->where('user_id', intval($userId));
        }

        if ($ip) {
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        if ($startTime) {
            $query->where('created_at', '>=', $startTime);
        }

        if ($endTime) {
            $query->where('created_at', '<=', $endTime);
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $log) {
            $item = $log->toArray();
            $item['username'] = $log->user ? $log->user->username : '';
            $item['request_data_array'] = $log->request_data ? json_decode($log->request_data, true) : [];
            $item['is_success'] = $log->action === 'login_success';
            unset($item['user']);
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

    public function api(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $appId = $request->param('app_id', '');
        $apiPath = $request->param('api_path', '');
        $ip = $request->param('ip', '');
        $startTime = $request->param('start_time', '');
        $endTime = $request->param('end_time', '');

        $query = ApiLog::with(['app'])->order('id', 'desc');

        if ($appId) {
            $query->where('app_id', intval($appId));
        }

        if ($apiPath) {
            $query->where('api_path', 'like', '%' . $apiPath . '%');
        }

        if ($ip) {
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        if ($startTime) {
            $query->where('created_at', '>=', $startTime);
        }

        if ($endTime) {
            $query->where('created_at', '<=', $endTime);
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $log) {
            $item = $log->toArray();
            $item['app_name'] = $log->app ? $log->app->name : '';
            unset($item['app']);
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
}
