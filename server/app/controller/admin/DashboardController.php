<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\StatService;
use think\Request;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $data = StatService::getAdminDashboard();
        return success($data, '获取成功');
    }

    public function overview(Request $request)
    {
        $data = StatService::getAdminDashboard();
        return success($data, '获取成功');
    }

    public function trend(Request $request)
    {
        $range = $request->param('range', 'week');
        if (!in_array($range, ['week', 'month'])) {
            $range = 'week';
        }

        $data = StatService::getAdminTrend($range);
        return success($data, '获取成功');
    }
}
