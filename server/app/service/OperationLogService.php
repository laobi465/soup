<?php
declare (strict_types = 1);

namespace app\service;

use app\model\OperationLog;
use think\Request;

class OperationLogService
{
    public static function log(
        int $userId,
        string $action,
        string $targetType = '',
        int $targetId = 0,
        array $requestData = [],
        string $ip = '',
        string $userAgent = ''
    ): void {
        $log = new OperationLog();
        $log->user_id = $userId;
        $log->action = $action;
        $log->target_type = $targetType;
        $log->target_id = $targetId;
        $log->ip = $ip;
        $log->user_agent = $userAgent;
        $log->request_data = !empty($requestData) ? json_encode($requestData, JSON_UNESCAPED_UNICODE) : '';
        $log->save();
    }

    public static function logFromRequest(
        Request $request,
        string $action,
        string $targetType = '',
        int $targetId = 0,
        array $extraData = []
    ): void {
        $userId = $request->user_id ?? 0;
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent', '');

        $requestData = array_merge($request->param(), $extraData);

        self::log($userId, $action, $targetType, $targetId, $requestData, $ip, $userAgent);
    }

    public static function getList(array $filters = [], int $page = 1, int $pageSize = 10): array
    {
        $query = OperationLog::with(['user'])->order('id', 'desc');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', intval($filters['user_id']));
        }

        if (!empty($filters['action'])) {
            $query->where('action', 'like', '%' . $filters['action'] . '%');
        }

        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (!empty($filters['target_id'])) {
            $query->where('target_id', intval($filters['target_id']));
        }

        if (!empty($filters['ip'])) {
            $query->where('ip', 'like', '%' . $filters['ip'] . '%');
        }

        if (!empty($filters['start_time'])) {
            $query->where('created_at', '>=', $filters['start_time']);
        }

        if (!empty($filters['end_time'])) {
            $query->where('created_at', '<=', $filters['end_time']);
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
            unset($item['user']);
            $items[] = $item;
        }

        return [
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ];
    }
}
