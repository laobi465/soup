<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\Agent;
use app\model\Merchant;
use app\model\Order;
use app\model\WalletTransaction;
use think\Request;

class AgentController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $level = $request->param('level', '');
        $status = $request->param('status', '');
        $keyword = $request->param('keyword', '');

        $query = Agent::where('merchant_id', $merchant->id)
            ->with(['user', 'parentAgent'])
            ->order('id', 'desc');

        if ($level !== '') {
            $query->where('level', intval($level));
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        if ($keyword) {
            $query->whereHas('user', function ($q) use ($keyword) {
                $q->where('username', 'like', '%' . $keyword . '%')
                    ->whereOr('email', 'like', '%' . $keyword . '%');
            });
        }

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'username' => $item->user ? $item->user->username : '',
                'email' => $item->user ? $item->user->email : '',
                'parent_agent_id' => $item->parent_agent_id,
                'parent_username' => $item->parentAgent && $item->parentAgent->user ? $item->parentAgent->user->username : '',
                'level' => $item->level,
                'level_text' => $item->level_text,
                'invite_code' => $item->invite_code,
                'purchase_price_rate' => $item->purchase_price_rate,
                'commission_rate' => $item->commission_rate,
                'total_earnings' => $item->total_earnings,
                'available_balance' => $item->available_balance,
                'frozen_balance' => $item->frozen_balance,
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

    public function read(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $agent = Agent::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->with(['user', 'parentAgent'])
            ->find();

        if (!$agent) {
            return error('代理不存在', 404);
        }

        $childCount = Agent::where('parent_agent_id', $agent->id)->count();

        return success([
            'id' => $agent->id,
            'user_id' => $agent->user_id,
            'username' => $agent->user ? $agent->user->username : '',
            'email' => $agent->user ? $agent->user->email : '',
            'parent_agent_id' => $agent->parent_agent_id,
            'parent_username' => $agent->parentAgent && $agent->parentAgent->user ? $agent->parentAgent->user->username : '',
            'level' => $agent->level,
            'level_text' => $agent->level_text,
            'invite_code' => $agent->invite_code,
            'invite_url' => $agent->invite_url,
            'purchase_price_rate' => $agent->purchase_price_rate,
            'commission_rate' => $agent->commission_rate,
            'total_earnings' => $agent->total_earnings,
            'available_balance' => $agent->available_balance,
            'frozen_balance' => $agent->frozen_balance,
            'child_count' => $childCount,
            'status' => $agent->status,
            'status_text' => $agent->status_text,
            'created_at' => $agent->created_at,
        ], '获取成功');
    }

    public function tree(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $agents = Agent::where('merchant_id', $merchant->id)
            ->with(['user'])
            ->order('level', 'asc')
            ->select();

        $agentMap = [];
        foreach ($agents as $agent) {
            $agentMap[$agent->id] = [
                'id' => $agent->id,
                'user_id' => $agent->user_id,
                'username' => $agent->user ? $agent->user->username : '',
                'level' => $agent->level,
                'level_text' => $agent->level_text,
                'total_earnings' => $agent->total_earnings,
                'status' => $agent->status,
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($agents as $agent) {
            if ($agent->parent_agent_id > 0 && isset($agentMap[$agent->parent_agent_id])) {
                $agentMap[$agent->parent_agent_id]['children'][] = &$agentMap[$agent->id];
            } else {
                $tree[] = &$agentMap[$agent->id];
            }
        }

        return success($tree, '获取成功');
    }

    public function updateLevel(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $agent = Agent::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$agent) {
            return error('代理不存在', 404);
        }

        $commissionRate = $request->param('commission_rate', '');
        $purchasePriceRate = $request->param('purchase_price_rate', '');

        if ($commissionRate !== '') {
            $rate = floatval($commissionRate);
            if ($rate < 0 || $rate > 1) {
                return error('佣金比例应在0-1之间', 400);
            }
            $agent->commission_rate = number_format($rate, 2, '.', '');
        }

        if ($purchasePriceRate !== '') {
            $rate = floatval($purchasePriceRate);
            if ($rate < 0 || $rate > 1) {
                return error('拿货折扣率应在0-1之间', 400);
            }
            $agent->purchase_price_rate = number_format($rate, 2, '.', '');
        }

        $agent->save();

        return success(null, '更新成功');
    }

    public function updateStatus(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $agent = Agent::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$agent) {
            return error('代理不存在', 404);
        }

        $status = intval($request->param('status', 0));
        $agent->status = $status;
        $agent->save();

        return success(null, $status == 1 ? '代理已启用' : '代理已禁用');
    }

    public function orders(Request $request, $id)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $agent = Agent::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->find();

        if (!$agent) {
            return error('代理不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);

        $query = Order::where('agent_id', $id)
            ->order('id', 'desc');

        $list = $query->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        $items = [];
        foreach ($list->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'order_no' => $item->order_no,
                'type' => $item->type,
                'type_text' => $item->type_text,
                'amount' => $item->amount,
                'pay_status' => $item->pay_status,
                'status_text' => $item->status_text,
                'commission_amount' => $item->commission_amount,
                'pay_time' => $item->pay_time,
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

    public function commission(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $merchant = Merchant::where('user_id', $userId)->find();
        if (!$merchant) {
            return error('商户不存在', 404);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $agentId = $request->param('agent_id', '');
        $settleStatus = $request->param('settle_status', '');

        $agentIds = Agent::where('merchant_id', $merchant->id)->column('id');
        if (empty($agentIds)) {
            return success([
                'list' => [],
                'total' => 0,
                'page' => 1,
                'pageSize' => $pageSize,
            ], '获取成功');
        }

        $walletIds = \app\model\Wallet::whereIn('user_id', function ($q) use ($merchant) {
            $q->name('agents')
                ->where('merchant_id', $merchant->id)
                ->column('user_id');
        })->where('type', 2)->column('id');

        if (empty($walletIds)) {
            return success([
                'list' => [],
                'total' => 0,
                'page' => 1,
                'pageSize' => $pageSize,
            ], '获取成功');
        }

        $query = WalletTransaction::whereIn('wallet_id', $walletIds)
            ->whereIn('type', [4, 5])
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
                'wallet_id' => $item->wallet_id,
                'type' => $item->type,
                'type_text' => $item->type_text,
                'amount' => $item->amount,
                'related_order' => $item->related_order,
                'balance_after' => $item->balance_after,
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
}
