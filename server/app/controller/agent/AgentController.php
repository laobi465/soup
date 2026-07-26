<?php
declare (strict_types = 1);

namespace app\controller\agent;

use app\BaseController;
use app\model\Agent;
use app\model\Order;
use app\model\Wallet;
use app\model\WalletTransaction;
use app\service\WithdrawService;
use think\Request;

class AgentController extends BaseController
{
    public function dashboard(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $agent = Agent::where('user_id', $userId)->find();
        if (!$agent) {
            return error('代理不存在', 404);
        }

        $teamCount = Agent::where('parent_agent_id', $agent->id)->count();

        $todayOrders = Order::where('agent_id', $agent->id)
            ->where('pay_status', Order::STATUS_PAID)
            ->whereTime('created_at', 'today')
            ->count();

        $todayCommission = Order::where('agent_id', $agent->id)
            ->where('pay_status', Order::STATUS_PAID)
            ->whereTime('created_at', 'today')
            ->sum('commission_amount');

        $totalOrders = Order::where('agent_id', $agent->id)
            ->where('pay_status', Order::STATUS_PAID)
            ->count();

        return success([
            'agent_info' => [
                'id' => $agent->id,
                'level' => $agent->level,
                'level_text' => $agent->level_text,
                'invite_code' => $agent->invite_code,
                'commission_rate' => $agent->commission_rate,
                'total_earnings' => $agent->total_earnings,
                'available_balance' => $agent->available_balance,
                'frozen_balance' => $agent->frozen_balance,
            ],
            'team_count' => $teamCount,
            'today_orders' => $todayOrders,
            'today_commission' => $todayCommission ?: '0.00',
            'total_orders' => $totalOrders,
        ], '获取成功');
    }

    public function invite(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $agent = Agent::where('user_id', $userId)->find();
        if (!$agent) {
            return error('代理不存在', 404);
        }

        if (!$agent->canInvite()) {
            return error('您无法发展下级代理', 403);
        }

        $inviteUrl = request()->domain() . '/register?invite_code=' . $agent->invite_code;

        return success([
            'invite_code' => $agent->invite_code,
            'invite_url' => $inviteUrl,
            'level' => $agent->level,
            'can_invite' => $agent->canInvite(),
        ], '获取成功');
    }

    public function team(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $agent = Agent::where('user_id', $userId)->find();
        if (!$agent) {
            return error('代理不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $level = $request->param('level', '');

        $allChildIds = $this->getAllChildAgentIds($agent->id);

        if (empty($allChildIds)) {
            return success([
                'list' => [],
                'total' => 0,
                'page' => 1,
                'pageSize' => $pageSize,
            ], '获取成功');
        }

        $query = Agent::whereIn('id', $allChildIds)
            ->with(['user'])
            ->order('id', 'desc');

        if ($level !== '') {
            $query->where('level', intval($level));
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'username' => $item->user ? $item->user->username : '',
                'email' => $item->user ? $item->user->email : '',
                'level' => $item->level,
                'level_text' => $item->level_text,
                'total_earnings' => $item->total_earnings,
                'status' => $item->status,
                'status_text' => $item->status_text,
                'created_at' => $item->created_at,
            ];
        }

        return success([
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ], '获取成功');
    }

    protected function getAllChildAgentIds(int $agentId): array
    {
        $ids = [];
        $queue = [$agentId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $children = Agent::where('parent_agent_id', $currentId)->column('id');
            foreach ($children as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    public function commission(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $wallet = Wallet::where('user_id', $userId)->where('type', 2)->find();
        if (!$wallet) {
            return success([
                'list' => [],
                'total' => 0,
                'page' => 1,
                'pageSize' => 10,
            ], '获取成功');
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $settleStatus = $request->param('settle_status', '');

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->whereIn('type', [4, 5, 1])
            ->order('id', 'desc');

        if ($settleStatus !== '') {
            $query->where('settle_status', intval($settleStatus));
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'type' => $item->type,
                'type_text' => $item->type_text,
                'amount' => $item->amount,
                'related_order' => $item->related_order,
                'settle_date' => $item->settle_date,
                'settle_status' => $item->settle_status,
                'settle_status_text' => $item->settle_status_text,
                'remark' => $item->remark,
                'created_at' => $item->created_at,
            ];
        }

        return success([
            'list' => $items,
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
        ], '获取成功');
    }

    public function wallet(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $agent = Agent::where('user_id', $userId)->find();
        if (!$agent) {
            return error('代理不存在', 404);
        }

        $wallet = Wallet::where('user_id', $userId)->where('type', 2)->find();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $userId;
            $wallet->type = 2;
            $wallet->balance = 0;
            $wallet->frozen = 0;
            $wallet->save();
        }

        $available = bcsub(strval($wallet->balance), strval($wallet->frozen), 2);

        return success([
            'wallet' => [
                'balance' => $wallet->balance,
                'frozen' => $wallet->frozen,
                'available' => $available,
            ],
            'agent' => [
                'total_earnings' => $agent->total_earnings,
                'available_balance' => $agent->available_balance,
                'frozen_balance' => $agent->frozen_balance,
            ],
        ], '获取成功');
    }

    public function withdraw(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $agent = Agent::where('user_id', $userId)->find();
        if (!$agent) {
            return error('代理不存在', 404);
        }

        $amount = floatval($request->param('amount', 0));
        $account = $request->param('account', '');

        if ($amount <= 0) {
            return error('提现金额无效', 400);
        }

        if (empty($account)) {
            return error('请填写收款账户', 400);
        }

        $withdrawService = new WithdrawService();
        $result = $withdrawService->applyWithdraw($userId, $amount, $account, 2);

        if ($result['success']) {
            return success($result, '提现申请已提交');
        } else {
            return error($result['message'] ?? '申请失败', 500);
        }
    }

    public function withdrawList(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);

        $withdrawService = new WithdrawService();
        $result = $withdrawService->getWithdrawList($userId, 2, $page, $pageSize);

        return success($result, '获取成功');
    }
}
