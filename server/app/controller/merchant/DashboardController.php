<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\service\StatService;
use think\Request;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $data = StatService::getMerchantDashboard($merchantId);
        return success($data, '获取成功');
    }

    public function cardStats(Request $request)
    {
        $appId = $request->param('app_id', 0);
        $merchantId = $request->merchant_id;

        if (!$appId) {
            return error('应用ID不能为空', 400);
        }

        $app = \app\model\App::where('id', $appId)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$app) {
            return error('应用不存在', 404);
        }

        $data = StatService::getCardStats($appId);
        return success($data, '获取成功');
    }

    public function apiStats(Request $request)
    {
        $appId = $request->param('app_id', 0);
        $range = $request->param('range', 'day');
        $merchantId = $request->merchant_id;

        if (!$appId) {
            return error('应用ID不能为空', 400);
        }

        if (!in_array($range, ['day', 'week', 'month'])) {
            $range = 'day';
        }

        $app = \app\model\App::where('id', $appId)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$app) {
            return error('应用不存在', 404);
        }

        $data = StatService::getApiStats($appId, $range);
        return success($data, '获取成功');
    }
}
