<?php
declare (strict_types = 1);

namespace app\controller\api;

use app\BaseController;
use app\model\Card;
use app\model\ApiLog;
use app\model\App;
use app\service\CardService;
use think\Request;

class CardApiController extends BaseController
{
    protected $startTime;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->startTime = microtime(true);
    }

    public function verify(Request $request)
    {
        $appId = $request->app_id ?? 0;
        $cardNo = $request->param('card_no', '');
        $deviceFingerprint = $request->param('device_fingerprint', '');
        $deviceName = $request->param('device_name', '');
        $ip = $request->ip();

        if (empty($cardNo)) {
            $this->logApi($request, 'verify', 0, 4101);
            return $this->apiError(4101, '卡密不能为空');
        }

        $cardNoHash = CardService::hashCardNo($cardNo);
        $card = CardService::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            $this->logApi($request, 'verify', 0, 4101);
            return $this->apiError(4101, '卡密不存在');
        }

        if (!CardService::checkBruteForce($card->id, $ip)) {
            $this->logApi($request, 'verify', $card->id, 4104);
            return $this->apiError(4104, '卡密已封禁（防爆破）');
        }

        if ($card->status == Card::STATUS_UNUSED) {
            $this->logApi($request, 'verify', $card->id, 4102);
            CardService::recordBruteForceFail($card->id, $ip);
            return $this->apiError(4102, '卡密未激活');
        }

        if ($card->status == Card::STATUS_BANNED) {
            $this->logApi($request, 'verify', $card->id, 4104);
            return $this->apiError(4104, '卡密已封禁');
        }

        if ($card->status == Card::STATUS_VOIDED) {
            $this->logApi($request, 'verify', $card->id, 4105);
            return $this->apiError(4105, '卡密已作废');
        }

        if ($card->isExpired() && !$card->isSoftExpired()) {
            $this->logApi($request, 'verify', $card->id, 4103);
            return $this->apiError(4103, '卡密已到期');
        }

        $app = App::find($card->app_id);
        if (!$app || $app->status != 1) {
            $this->logApi($request, 'verify', $card->id, 4107);
            return $this->apiError(4107, '应用已停用');
        }

        if (!empty($deviceFingerprint)) {
            $bindResult = CardService::bindDevice($card->id, $deviceFingerprint, $deviceName);
            if (!$bindResult['success']) {
                $this->logApi($request, 'verify', $card->id, $bindResult['code'] ?? 4106);
                return $this->apiError($bindResult['code'] ?? 4106, $bindResult['message']);
            }
        }

        CardService::clearBruteForce($card->id, $ip);

        $deviceCount = \app\model\Device::where('card_id', $card->id)->count();
        $remainingDuration = 0;
        if ($card->card_type != Card::TYPE_PERMANENT && $card->expire_time) {
            $remainingDuration = max(0, strtotime($card->expire_time) - time());
        }

        $this->logApi($request, 'verify', $card->id, 0);

        return $this->apiSuccess([
            'card_id' => $card->id,
            'card_type' => $card->card_type,
            'card_type_text' => $card->card_type_text,
            'status' => $card->status,
            'status_text' => $card->status_text,
            'expire_time' => $card->expire_time,
            'remaining_duration' => $remainingDuration,
            'bind_device_count' => $deviceCount,
            'bind_limit' => $app->bind_limit,
            'is_permanent' => $card->card_type == Card::TYPE_PERMANENT,
            'is_soft_expired' => $card->isSoftExpired(),
        ]);
    }

    public function activate(Request $request)
    {
        $appId = $request->app_id ?? 0;
        $cardNo = $request->param('card_no', '');
        $deviceFingerprint = $request->param('device_fingerprint', '');
        $deviceName = $request->param('device_name', '');
        $ip = $request->ip();

        if (empty($cardNo)) {
            $this->logApi($request, 'activate', 0, 4101);
            return $this->apiError(4101, '卡密不能为空');
        }

        if (empty($deviceFingerprint)) {
            $this->logApi($request, 'activate', 0, 400);
            return $this->apiError(400, '设备指纹不能为空');
        }

        $cardNoHash = CardService::hashCardNo($cardNo);
        $card = CardService::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            $this->logApi($request, 'activate', 0, 4101);
            return $this->apiError(4101, '卡密不存在');
        }

        if (!CardService::checkBruteForce($card->id, $ip)) {
            $this->logApi($request, 'activate', $card->id, 4104);
            return $this->apiError(4104, '卡密已封禁（防爆破）');
        }

        if ($card->status == Card::STATUS_BANNED) {
            $this->logApi($request, 'activate', $card->id, 4104);
            return $this->apiError(4104, '卡密已封禁');
        }

        if ($card->status == Card::STATUS_VOIDED) {
            $this->logApi($request, 'activate', $card->id, 4105);
            return $this->apiError(4105, '卡密已作废');
        }

        $app = App::find($card->app_id);
        if (!$app || $app->status != 1) {
            $this->logApi($request, 'activate', $card->id, 4107);
            return $this->apiError(4107, '应用已停用');
        }

        $result = CardService::activateCard($cardNo, $appId, $deviceFingerprint, $deviceName);

        if (!$result['success']) {
            if (in_array($result['code'] ?? 0, [4102, 4101])) {
                CardService::recordBruteForceFail($card->id, $ip);
            }
            $this->logApi($request, 'activate', $card->id, $result['code'] ?? 400);
            return $this->apiError($result['code'] ?? 400, $result['message']);
        }

        CardService::clearBruteForce($card->id, $ip);

        $this->logApi($request, 'activate', $card->id, 0);

        return $this->apiSuccess($result['data']);
    }

    public function rebind(Request $request)
    {
        $appId = $request->app_id ?? 0;
        $cardNo = $request->param('card_no', '');
        $oldDevice = $request->param('old_device', '');
        $newDevice = $request->param('new_device', '');
        $deviceName = $request->param('device_name', '');

        if (empty($cardNo)) {
            $this->logApi($request, 'rebind', 0, 4101);
            return $this->apiError(4101, '卡密不能为空');
        }

        if (empty($oldDevice) || empty($newDevice)) {
            $this->logApi($request, 'rebind', 0, 400);
            return $this->apiError(400, '旧设备和新设备指纹不能为空');
        }

        $result = CardService::rebindDevice($cardNo, $appId, $oldDevice, $newDevice, $deviceName);

        $cardId = 0;
        $cardNoHash = CardService::hashCardNo($cardNo);
        $card = CardService::getCardByHash($cardNoHash, $appId);
        if ($card) {
            $cardId = $card->id;
        }

        $this->logApi($request, 'rebind', $cardId, $result['code'] ?? 0);

        if (!$result['success']) {
            return $this->apiError($result['code'] ?? 400, $result['message']);
        }

        return $this->apiSuccess($result['data']);
    }

    public function query(Request $request)
    {
        $appId = $request->app_id ?? 0;
        $cardNo = $request->param('card_no', '');

        if (empty($cardNo)) {
            $this->logApi($request, 'query', 0, 4101);
            return $this->apiError(4101, '卡密不能为空');
        }

        $cardNoHash = CardService::hashCardNo($cardNo);
        $card = CardService::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            $this->logApi($request, 'query', 0, 4101);
            return $this->apiError(4101, '卡密不存在');
        }

        $app = App::find($card->app_id);
        $deviceCount = \app\model\Device::where('card_id', $card->id)->count();
        $remainingDuration = 0;
        if ($card->card_type != Card::TYPE_PERMANENT && $card->expire_time) {
            $remainingDuration = max(0, strtotime($card->expire_time) - time());
        }

        $this->logApi($request, 'query', $card->id, 0);

        return $this->apiSuccess([
            'card_type' => $card->card_type,
            'card_type_text' => $card->card_type_text,
            'status' => $card->status,
            'status_text' => $card->status_text,
            'expire_time' => $card->expire_time,
            'remaining_duration' => $remainingDuration,
            'bind_device_count' => $deviceCount,
            'bind_limit' => $app ? $app->bind_limit : 1,
            'is_permanent' => $card->card_type == Card::TYPE_PERMANENT,
            'activate_time' => $card->activate_time,
        ]);
    }

    public function heartbeat(Request $request)
    {
        $appId = $request->app_id ?? 0;
        $cardNo = $request->param('card_no', '');
        $deviceFingerprint = $request->param('device_fingerprint', '');

        if (empty($cardNo) || empty($deviceFingerprint)) {
            return $this->apiError(400, '参数不完整');
        }

        $result = CardService::heartbeat($cardNo, $appId, $deviceFingerprint);

        $cardId = 0;
        $cardNoHash = CardService::hashCardNo($cardNo);
        $card = CardService::getCardByHash($cardNoHash, $appId);
        if ($card) {
            $cardId = $card->id;
        }

        $this->logApi($request, 'heartbeat', $cardId, $result['code'] ?? 0);

        if (!$result['success']) {
            return $this->apiError($result['code'] ?? 400, $result['message']);
        }

        return $this->apiSuccess($result['data']);
    }

    public function onlineCount(Request $request)
    {
        $appId = $request->app_id ?? 0;

        $count = CardService::getOnlineCount($appId);

        $this->logApi($request, 'online_count', 0, 0);

        return $this->apiSuccess([
            'online_count' => $count,
            'app_id' => $appId,
        ]);
    }

    public function announcement(Request $request)
    {
        $appId = $request->app_id ?? 0;

        $announcement = \think\facade\Cache::get('app_announcement_' . $appId);

        if (!$announcement) {
            $announcement = [
                'title' => '系统公告',
                'content' => '',
                'enabled' => false,
                'variables' => [],
            ];
        }

        $this->logApi($request, 'announcement', 0, 0);

        return $this->apiSuccess($announcement);
    }

    public function register(Request $request)
    {
        return $this->apiError(400, '暂未开放用户注册功能');
    }

    public function login(Request $request)
    {
        return $this->apiError(400, '暂未开放用户登录功能');
    }

    protected function apiSuccess($data = null): \think\Response
    {
        return json([
            'code' => 0,
            'message' => 'success',
            'data' => $data,
            'timestamp' => time(),
        ]);
    }

    protected function apiError(int $code, string $message): \think\Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => null,
            'timestamp' => time(),
        ]);
    }

    protected function logApi(Request $request, string $apiType, int $cardId, int $responseCode): void
    {
        try {
            $costMs = intval((microtime(true) - $this->startTime) * 1000);
            $appId = $request->app_id ?? 0;
            $ip = $request->ip();
            $device = $request->param('device_fingerprint', '') ?: ($request->param('device', '') ?? '');
            $requestData = json_encode($request->param(), JSON_UNESCAPED_UNICODE);

            $log = new ApiLog();
            $log->app_id = $appId;
            $log->card_id = $cardId;
            $log->ip = $ip;
            $log->device = $device;
            $log->api_type = $apiType;
            $log->request_data = $requestData;
            $log->response_code = $responseCode;
            $log->cost_ms = $costMs;
            $log->save();
        } catch (\Exception $e) {
        }
    }
}
