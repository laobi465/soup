<?php
declare (strict_types = 1);

namespace app\controller\common;

use app\BaseController;
use app\service\MessageService;
use think\Request;

class MessageController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id;
        if (!$userId) {
            return error('请先登录', 401);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $type = $request->param('type', '');
        $isRead = $request->param('is_read', '');

        $filters = [
            'type' => $type,
            'is_read' => $isRead,
        ];

        $data = MessageService::getMessageList($userId, intval($page), intval($pageSize), $filters);
        return success($data, '获取成功');
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->user_id;
        if (!$userId) {
            return error('请先登录', 401);
        }

        $count = MessageService::getUnreadCount($userId);
        return success(['count' => $count], '获取成功');
    }

    public function markAsRead(Request $request, $id)
    {
        $userId = $request->user_id;
        if (!$userId) {
            return error('请先登录', 401);
        }

        $result = MessageService::markAsRead(intval($id), $userId);
        if (!$result) {
            return error('消息不存在', 404);
        }

        return success(null, '标记成功');
    }

    public function markAllAsRead(Request $request)
    {
        $userId = $request->user_id;
        if (!$userId) {
            return error('请先登录', 401);
        }

        $count = MessageService::markAllAsRead($userId);
        return success(['count' => $count], '全部已读成功');
    }
}
