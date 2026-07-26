<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Card;
use app\model\Device;
use app\service\CardService;
use think\Request;

class CardController extends BaseController
{
    public function index(Request $request)
    {
        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $appId = $request->param('app_id', 0);
        $merchantId = $request->param('merchant_id', 0);
        $cardType = $request->param('card_type', '');
        $status = $request->param('status', '');
        $keyword = $request->param('keyword', '');
        $startTime = $request->param('start_time', '');
        $endTime = $request->param('end_time', '');

        $query = Card::with(['app', 'merchant']);

        if ($appId > 0) {
            $query->where('app_id', intval($appId));
        }

        if ($merchantId > 0) {
            $query->where('merchant_id', intval($merchantId));
        }

        if ($cardType !== '') {
            $query->where('card_type', intval($cardType));
        }

        if ($status !== '') {
            $query->where('status', intval($status));
        }

        if ($keyword !== '') {
            $query->whereLike('card_no_prefix', '%' . $keyword . '%');
        }

        if ($startTime) {
            $query->where('created_at', '>=', $startTime);
        }

        if ($endTime) {
            $query->where('created_at', '<=', $endTime);
        }

        $list = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        $stats = [
            'total' => Card::count(),
            'unused' => Card::where('status', Card::STATUS_UNUSED)->count(),
            'activated' => Card::where('status', Card::STATUS_ACTIVATED)->count(),
            'expired' => Card::where('status', Card::STATUS_EXPIRED)->count(),
            'banned' => Card::where('status', Card::STATUS_BANNED)->count(),
            'voided' => Card::where('status', Card::STATUS_VOIDED)->count(),
        ];

        $data = [
            'list' => $list->items(),
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'pageSize' => $list->listRows(),
            'stats' => $stats,
        ];

        return success($data, '获取成功');
    }

    public function read(Request $request, $id)
    {
        $card = Card::where('id', $id)
            ->with(['app', 'merchant', 'batch'])
            ->find();

        if (!$card) {
            return error('卡密不存在', 404);
        }

        $devices = Device::where('card_id', $id)
            ->order('bind_time', 'desc')
            ->select();

        $data = $card->toArray();
        $data['devices'] = $devices->toArray();
        $data['device_count'] = count($devices);

        return success($data, '获取成功');
    }

    public function ban(Request $request, $id)
    {
        $card = Card::find($id);
        if (!$card) {
            return error('卡密不存在', 404);
        }

        $reason = $request->param('reason', '');
        $result = CardService::banCard($card->id, $reason);

        if (!$result['success']) {
            return error($result['message'], 400);
        }

        return success(null, '封禁成功');
    }

    public function unban(Request $request, $id)
    {
        $card = Card::find($id);
        if (!$card) {
            return error('卡密不存在', 404);
        }

        $result = CardService::unbanCard($card->id);

        if (!$result['success']) {
            return error($result['message'], 400);
        }

        return success(null, '解封成功');
    }
}
