<?php
declare (strict_types = 1);

namespace app\controller\merchant;

use app\BaseController;
use app\model\Card;
use app\model\CardBatch;
use app\model\Device;
use app\model\App;
use app\model\OperationLog;
use app\service\CardService;
use think\Request;

class CardController extends BaseController
{
    public function index(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $page = $request->param('page', 1);
        $pageSize = $request->param('pageSize', 10);
        $appId = $request->param('app_id', 0);
        $cardType = $request->param('card_type', '');
        $status = $request->param('status', '');
        $keyword = $request->param('keyword', '');
        $startTime = $request->param('start_time', '');
        $endTime = $request->param('end_time', '');

        $query = Card::where('merchant_id', $merchantId);

        if ($appId > 0) {
            $query->where('app_id', intval($appId));
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

        $list = $query->with(['app'])
            ->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $pageSize,
            ]);

        $stats = [
            'total' => Card::where('merchant_id', $merchantId)->count(),
            'unused' => Card::where('merchant_id', $merchantId)->where('status', Card::STATUS_UNUSED)->count(),
            'activated' => Card::where('merchant_id', $merchantId)->where('status', Card::STATUS_ACTIVATED)->count(),
            'expired' => Card::where('merchant_id', $merchantId)->where('status', Card::STATUS_EXPIRED)->count(),
            'banned' => Card::where('merchant_id', $merchantId)->where('status', Card::STATUS_BANNED)->count(),
            'voided' => Card::where('merchant_id', $merchantId)->where('status', Card::STATUS_VOIDED)->count(),
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
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $card = Card::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->with(['app', 'batch'])
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

    public function generate(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $data = $request->param();

        $this->validate($data, [
            'app_id' => 'require|integer|>:0',
            'card_type' => 'require|integer|in:1,2,3,4,5,6,7',
            'duration' => 'integer|>=:0',
            'prefix' => 'max:20',
            'length' => 'integer|>=:16|<=:32',
        ], [
            'app_id.require' => '应用ID不能为空',
            'card_type.require' => '卡密类型不能为空',
            'card_type.in' => '卡密类型无效',
            'length.>=' => '卡密长度不能小于16位',
            'length.<=' => '卡密长度不能大于32位',
        ]);

        $app = App::where('id', $data['app_id'])
            ->where('merchant_id', $merchantId)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        if ($app->status != 1) {
            return error('应用已停用', 400);
        }

        $cardType = intval($data['card_type']);
        $duration = intval($data['duration'] ?? 0);

        if ($cardType != 6 && $duration <= 0) {
            $typeDurations = [
                1 => 86400,
                2 => 86400 * 7,
                3 => 86400 * 30,
                4 => 86400 * 90,
                5 => 86400 * 365,
                7 => 86400,
            ];
            $duration = $typeDurations[$cardType] ?? 86400 * 30;
        }

        $params = [
            'card_type' => $cardType,
            'duration' => $duration,
            'prefix' => $data['prefix'] ?? '',
            'length' => intval($data['length'] ?? 16),
            'custom_charset' => $data['custom_charset'] ?? '',
            'created_by' => $userId,
            'remark' => $data['remark'] ?? '',
        ];

        try {
            $result = CardService::generateSingle(intval($data['app_id']), $merchantId, $params);
        } catch (\Exception $e) {
            return error($e->getMessage(), 400);
        }

        $this->logOperation($request, 'generate_card', 'card', $result['card']->id, [
            'app_id' => $data['app_id'],
            'card_type' => $cardType,
            'prefix' => $data['prefix'] ?? '',
        ]);

        $resultData = $result['card']->toArray();
        $resultData['card_no'] = $result['card_no'];

        return success($resultData, '生成成功');
    }

    public function batchGenerate(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $data = $request->param();

        $this->validate($data, [
            'app_id' => 'require|integer|>:0',
            'count' => 'require|integer|>=:1|<=:1000',
            'card_type' => 'require|integer|in:1,2,3,4,5,6,7',
            'duration' => 'integer|>=:0',
            'prefix' => 'max:20',
            'length' => 'integer|>=:16|<=:32',
        ], [
            'app_id.require' => '应用ID不能为空',
            'count.require' => '生成数量不能为空',
            'count.>=' => '生成数量不能小于1',
            'count.<=' => '单次最多生成1000张',
            'card_type.require' => '卡密类型不能为空',
            'card_type.in' => '卡密类型无效',
            'length.>=' => '卡密长度不能小于16位',
            'length.<=' => '卡密长度不能大于32位',
        ]);

        $app = App::where('id', $data['app_id'])
            ->where('merchant_id', $merchantId)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        if ($app->status != 1) {
            return error('应用已停用', 400);
        }

        $cardType = intval($data['card_type']);
        $duration = intval($data['duration'] ?? 0);

        if ($cardType != 6 && $duration <= 0) {
            $typeDurations = [
                1 => 86400,
                2 => 86400 * 7,
                3 => 86400 * 30,
                4 => 86400 * 90,
                5 => 86400 * 365,
                7 => 86400,
            ];
            $duration = $typeDurations[$cardType] ?? 86400 * 30;
        }

        $params = [
            'count' => intval($data['count']),
            'card_type' => $cardType,
            'duration' => $duration,
            'prefix' => $data['prefix'] ?? '',
            'length' => intval($data['length'] ?? 16),
            'custom_charset' => $data['custom_charset'] ?? '',
            'created_by' => $userId,
            'remark' => $data['remark'] ?? '',
        ];

        try {
            $result = CardService::generateBatch(intval($data['app_id']), $merchantId, $params);
        } catch (\Exception $e) {
            return error($e->getMessage(), 400);
        }

        $this->logOperation($request, 'batch_generate_card', 'card_batch', $result['batch_id'], [
            'app_id' => $data['app_id'],
            'count' => $data['count'],
            'card_type' => $cardType,
            'prefix' => $data['prefix'] ?? '',
        ]);

        return success($result, '批量生成成功');
    }

    public function ban(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $card = Card::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$card) {
            return error('卡密不存在', 404);
        }

        $reason = $request->param('reason', '');
        $result = CardService::banCard($card->id, $reason);

        if (!$result['success']) {
            return error($result['message'], 400);
        }

        $this->logOperation($request, 'ban_card', 'card', $card->id, [
            'reason' => $reason,
        ]);

        return success(null, '封禁成功');
    }

    public function unban(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $card = Card::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$card) {
            return error('卡密不存在', 404);
        }

        $result = CardService::unbanCard($card->id);

        if (!$result['success']) {
            return error($result['message'], 400);
        }

        $this->logOperation($request, 'unban_card', 'card', $card->id, []);

        return success(null, '解封成功');
    }

    public function void(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $card = Card::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$card) {
            return error('卡密不存在', 404);
        }

        $result = CardService::voidCard($card->id);

        if (!$result['success']) {
            return error($result['message'], 400);
        }

        $this->logOperation($request, 'void_card', 'card', $card->id, []);

        return success(null, '作废成功');
    }

    public function renew(Request $request, $id)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $card = Card::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$card) {
            return error('卡密不存在', 404);
        }

        $duration = intval($request->param('duration', 0));
        if ($duration <= 0) {
            return error('续时时长必须大于0', 400);
        }

        $result = CardService::renewCard($card->id, $duration);

        if (!$result['success']) {
            return error($result['message'], 400);
        }

        $this->logOperation($request, 'renew_card', 'card', $card->id, [
            'duration' => $duration,
        ]);

        return success($result['data'], '续费成功');
    }

    public function unbindDevice(Request $request, $id, $deviceId)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $card = Card::where('merchant_id', $merchantId)
            ->where('id', $id)
            ->find();

        if (!$card) {
            return error('卡密不存在', 404);
        }

        $result = CardService::unbindDevice($card->id, intval($deviceId));

        if (!$result) {
            return error('设备不存在或解绑失败', 400);
        }

        $this->logOperation($request, 'unbind_device', 'card', $card->id, [
            'device_id' => $deviceId,
        ]);

        return success(null, '解绑成功');
    }

    public function export(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        if (!$merchantId) {
            return error('商户信息不存在', 401);
        }

        $appId = $request->param('app_id', 0);
        $cardType = $request->param('card_type', '');
        $status = $request->param('status', '');
        $keyword = $request->param('keyword', '');
        $startTime = $request->param('start_time', '');
        $endTime = $request->param('end_time', '');

        $query = Card::where('merchant_id', $merchantId);

        if ($appId > 0) {
            $query->where('app_id', intval($appId));
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

        $cards = $query->with(['app'])
            ->order('id', 'desc')
            ->limit(10000)
            ->select();

        $rows = [];
        $rows[] = ['ID', '前缀', '应用', '类型', '状态', '创建时间', '激活时间', '到期时间'];

        foreach ($cards as $card) {
            $rows[] = [
                $card->id,
                $card->card_no_prefix ?: '-',
                $card->app ? $card->app->name : '-',
                $card->card_type_text,
                $card->status_text,
                $card->created_at,
                $card->activate_time ?: '-',
                $card->expire_time ?: '-',
            ];
        }

        $filename = 'cards_' . date('YmdHis') . '.csv';
        $content = '';
        foreach ($rows as $row) {
            $content .= implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\n";
        }

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function import(Request $request)
    {
        $merchantId = $request->merchant_id ?? 0;
        $userId = $request->user_id ?? 0;
        if (!$merchantId || !$userId) {
            return error('用户未登录', 401);
        }

        $appId = intval($request->param('app_id', 0));
        $cardType = intval($request->param('card_type', 3));
        $duration = intval($request->param('duration', 0));

        if ($appId <= 0) {
            return error('应用ID不能为空', 400);
        }

        $app = App::where('id', $appId)
            ->where('merchant_id', $merchantId)
            ->find();

        if (!$app) {
            return error('应用不存在', 404);
        }

        $file = $request->file('file');
        if (!$file) {
            return error('请上传文件', 400);
        }

        $content = file_get_contents($file->getPathname());
        if (empty($content)) {
            return error('文件内容为空', 400);
        }

        $cardNos = explode("\n", $content);
        $cardNos = array_map('trim', $cardNos);
        $cardNos = array_filter($cardNos);
        $cardNos = array_values($cardNos);

        if (empty($cardNos)) {
            return error('未找到有效的卡密', 400);
        }

        if (count($cardNos) > 1000) {
            return error('单次最多导入1000张', 400);
        }

        if ($cardType != 6 && $duration <= 0) {
            $typeDurations = [
                1 => 86400,
                2 => 86400 * 7,
                3 => 86400 * 30,
                4 => 86400 * 90,
                5 => 86400 * 365,
                7 => 86400,
            ];
            $duration = $typeDurations[$cardType] ?? 86400 * 30;
        }

        try {
            $result = CardService::importCards($appId, $merchantId, $cardNos, [
                'card_type' => $cardType,
                'duration' => $duration,
                'created_by' => $userId,
                'prefix' => $request->param('prefix', ''),
            ]);
        } catch (\Exception $e) {
            return error($e->getMessage(), 400);
        }

        $this->logOperation($request, 'import_card', 'app', $appId, [
            'count' => count($cardNos),
            'success_count' => $result['success_count'],
        ]);

        return success($result, '导入完成');
    }

    protected function logOperation(Request $request, string $action, string $targetType, int $targetId, array $data = [])
    {
        $userId = $request->user_id ?? 0;
        $log = new OperationLog();
        $log->user_id = $userId;
        $log->action = $action;
        $log->target_type = $targetType;
        $log->target_id = $targetId;
        $log->ip = $request->ip();
        $log->user_agent = $request->header('User-Agent', '');
        $log->request_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $log->save();
    }
}
