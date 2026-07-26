<?php
declare (strict_types = 1);

namespace app\controller\agent;

use app\BaseController;
use app\service\StatService;
use app\model\Agent;
use think\Request;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id;
        if (!$userId) {
            return error('用户信息不存在', 401);
        }

        $agent = Agent::where('user_id', $userId)->find();
        if (!$agent) {
            return error('代理信息不存在', 404);
        }

        $data = StatService::getAgentDashboard($agent->id);
        return success($data, '获取成功');
    }
}
