<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\RiskControlService;
use app\model\RiskBlacklist;
use think\Request;

class RiskController extends BaseController
{
    public function blacklistIndex(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $type = $request->param('type', '');
        $keyword = $request->param('keyword', '');
        $status = $request->param('status', '');

        $filters = [
            'type' => $type,
            'keyword' => $keyword,
            'status' => $status,
        ];

        $data = RiskControlService::getBlacklist($filters, intval($page), intval($pageSize));
        return success($data, '获取成功');
    }

    public function blacklistSave(Request $request)
    {
        $type = $request->param('type', 0);
        $value = $request->param('value', '');
        $reason = $request->param('reason', '');
        $expireTime = $request->param('expire_time', '');

        if (!$type || !$value) {
            return error('类型和值不能为空', 400);
        }

        if (!in_array($type, [1, 2, 3, 4])) {
            return error('黑名单类型无效', 400);
        }

        $item = RiskControlService::addBlacklist(
            intval($type),
            $value,
            $reason,
            $expireTime ?: null
        );

        return success($item, '添加成功');
    }

    public function blacklistUpdate(Request $request, $id)
    {
        $item = RiskBlacklist::find($id);
        if (!$item) {
            return error('黑名单记录不存在', 404);
        }

        $reason = $request->param('reason', '');
        $expireTime = $request->param('expire_time', '');

        $item->reason = $reason;
        $item->expire_time = $expireTime ?: null;
        $item->save();

        return success($item, '更新成功');
    }

    public function blacklistDelete(Request $request, $id)
    {
        $result = RiskControlService::removeBlacklist(intval($id));
        if (!$result) {
            return error('黑名单记录不存在', 404);
        }

        return success(null, '删除成功');
    }

    public function alerts(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);

        $alerts = [];

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $todayRegistrations = \app\model\User::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        if ($todayRegistrations > 50) {
            $alerts[] = [
                'id' => 1,
                'type' => 'register',
                'level' => 'warning',
                'title' => '注册量异常',
                'content' => "今日注册量达到 {$todayRegistrations}，超过正常阈值",
                'created_at' => date('Y-m-d H:i:s'),
                'is_read' => false,
            ];
        }

        $todayOrders = \app\model\Order::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->count();

        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $yesterdayOrders = \app\model\Order::where('created_at', '>=', $yesterdayStart)
            ->where('created_at', '<=', $yesterdayEnd)
            ->count();

        if ($yesterdayOrders > 0 && $todayOrders > $yesterdayOrders * 2) {
            $alerts[] = [
                'id' => 2,
                'type' => 'order',
                'level' => 'info',
                'title' => '订单量增长',
                'content' => "今日订单量 {$todayOrders}，较昨日增长显著",
                'created_at' => date('Y-m-d H:i:s'),
                'is_read' => false,
            ];
        }

        $total = count($alerts);
        $start = ($page - 1) * $pageSize;
        $list = array_slice($alerts, $start, $pageSize);

        $data = [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];

        return success($data, '获取成功');
    }

    public function overview(Request $request)
    {
        $data = RiskControlService::getRiskOverview();
        return success($data, '获取成功');
    }
}
