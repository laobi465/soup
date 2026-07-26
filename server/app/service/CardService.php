<?php
declare (strict_types = 1);

namespace app\service;

use app\library\Random;
use app\model\Card;
use app\model\CardBatch;
use app\model\Device;
use app\model\App;
use think\facade\Cache;
use think\facade\Db;

class CardService
{
    const DEFAULT_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    const CARD_CACHE_PREFIX = 'card:';
    const CARD_CACHE_TTL = 3600;

    const BRUTE_FORCE_PREFIX = 'bruteforce:';
    const BRUTE_FORCE_LIMIT = 15;
    const BRUTE_FORCE_WINDOW = 300;

    public static function generateCardNo(string $prefix = '', int $length = 16, string $charset = ''): string
    {
        if ($length < 16 || $length > 32) {
            throw new \InvalidArgumentException('卡密长度必须在16-32位之间');
        }

        $charset = $charset ?: self::DEFAULT_CHARSET;
        $cardNo = $prefix . Random::string($length, $charset);

        return $cardNo;
    }

    public static function hashCardNo(string $cardNo): string
    {
        return hash('sha256', $cardNo);
    }

    public static function generateBatch(int $appId, int $merchantId, array $params): array
    {
        $count = intval($params['count'] ?? 1);
        if ($count <= 0 || $count > 1000) {
            throw new \InvalidArgumentException('单次生成数量必须在1-1000之间');
        }

        $cardType = intval($params['card_type'] ?? 3);
        $duration = intval($params['duration'] ?? 0);
        $prefix = $params['prefix'] ?? '';
        $length = intval($params['length'] ?? 16);
        $charset = $params['custom_charset'] ?? '';
        $createdBy = intval($params['created_by'] ?? 0);
        $remark = $params['remark'] ?? '';

        if ($duration <= 0 && $cardType != Card::TYPE_PERMANENT) {
            throw new \InvalidArgumentException('时长必须大于0');
        }

        $app = App::find($appId);
        if (!$app) {
            throw new \InvalidArgumentException('应用不存在');
        }

        Db::startTrans();
        try {
            $batchNo = 'B' . date('YmdHis') . Random::numeric(6);

            $batch = new CardBatch();
            $batch->app_id = $appId;
            $batch->merchant_id = $merchantId;
            $batch->batch_no = $batchNo;
            $batch->card_type = $cardType;
            $batch->duration = $duration;
            $batch->count = $count;
            $batch->prefix = $prefix;
            $batch->length = $length;
            $batch->remark = $remark;
            $batch->created_by = $createdBy;
            $batch->save();

            $plainCards = [];
            $cardData = [];
            $now = date('Y-m-d H:i:s');

            for ($i = 0; $i < $count; $i++) {
                $cardNo = self::generateCardNo($prefix, $length, $charset);
                $cardNoHash = self::hashCardNo($cardNo);

                $plainCards[] = $cardNo;
                $cardData[] = [
                    'app_id' => $appId,
                    'merchant_id' => $merchantId,
                    'card_no_hash' => $cardNoHash,
                    'card_no_prefix' => $prefix,
                    'card_type' => $cardType,
                    'duration' => $duration,
                    'batch_id' => $batch->id,
                    'status' => Card::STATUS_UNUSED,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Db::name('cards')->insertAll($cardData);

            Db::commit();

            return [
                'batch_id' => $batch->id,
                'batch_no' => $batchNo,
                'count' => $count,
                'cards' => $plainCards,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function generateSingle(int $appId, int $merchantId, array $params): array
    {
        $params['count'] = 1;
        $result = self::generateBatch($appId, $merchantId, $params);

        $card = Card::where('batch_id', $result['batch_id'])->find();

        return [
            'card' => $card,
            'card_no' => $result['cards'][0] ?? '',
        ];
    }

    public static function getCardByHash(string $cardNoHash, int $appId = 0)
    {
        $cacheKey = self::CARD_CACHE_PREFIX . $cardNoHash;
        $card = Cache::get($cacheKey);

        if ($card) {
            if ($appId && $card['app_id'] != $appId) {
                return null;
            }
            return new Card($card);
        }

        $query = Card::where('card_no_hash', $cardNoHash);
        if ($appId) {
            $query->where('app_id', $appId);
        }

        $card = $query->find();
        if ($card) {
            Cache::set($cacheKey, $card->toArray(), self::CARD_CACHE_TTL);
        }

        return $card;
    }

    public static function clearCardCache(string $cardNoHash): void
    {
        Cache::delete(self::CARD_CACHE_PREFIX . $cardNoHash);
    }

    public static function verifyCard(string $cardNo, int $appId): array
    {
        $cardNoHash = self::hashCardNo($cardNo);
        $card = self::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status == Card::STATUS_BANNED) {
            return [
                'success' => false,
                'code' => 4104,
                'message' => '卡密已封禁',
            ];
        }

        if ($card->status == Card::STATUS_VOIDED) {
            return [
                'success' => false,
                'code' => 4105,
                'message' => '卡密已作废',
            ];
        }

        if ($card->isExpired() && !$card->isSoftExpired()) {
            return [
                'success' => false,
                'code' => 4103,
                'message' => '卡密已到期',
            ];
        }

        $app = App::find($card->app_id);
        if (!$app || $app->status != 1) {
            return [
                'success' => false,
                'code' => 4107,
                'message' => '应用已停用',
            ];
        }

        $deviceCount = Device::where('card_id', $card->id)->count();
        $remainingDuration = 0;
        if ($card->card_type != Card::TYPE_PERMANENT && $card->expire_time) {
            $remainingDuration = max(0, strtotime($card->expire_time) - time());
        }

        return [
            'success' => true,
            'code' => 0,
            'message' => 'success',
            'data' => [
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
            ],
        ];
    }

    public static function bindDevice(int $cardId, string $deviceFingerprint, string $deviceName = ''): array
    {
        $card = Card::find($cardId);
        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        $app = App::find($card->app_id);
        if (!$app) {
            return [
                'success' => false,
                'code' => 4107,
                'message' => '应用不存在',
            ];
        }

        $existingDevice = Device::where('card_id', $cardId)
            ->where('device_fingerprint', $deviceFingerprint)
            ->find();

        if ($existingDevice) {
            $existingDevice->last_heartbeat = date('Y-m-d H:i:s');
            $existingDevice->is_online = 1;
            if ($deviceName && !$existingDevice->device_name) {
                $existingDevice->device_name = $deviceName;
            }
            $existingDevice->save();

            return [
                'success' => true,
                'code' => 0,
                'message' => '设备已绑定',
                'data' => [
                    'device_id' => $existingDevice->id,
                    'is_new' => false,
                ],
            ];
        }

        $deviceCount = Device::where('card_id', $cardId)->count();
        if ($deviceCount >= $app->bind_limit) {
            return [
                'success' => false,
                'code' => 4106,
                'message' => '设备绑定数达上限',
            ];
        }

        $device = new Device();
        $device->card_id = $cardId;
        $device->app_id = $card->app_id;
        $device->device_fingerprint = $deviceFingerprint;
        $device->device_name = $deviceName;
        $device->bind_time = date('Y-m-d H:i:s');
        $device->last_heartbeat = date('Y-m-d H:i:s');
        $device->is_online = 1;
        $device->save();

        self::clearCardCache($card->card_no_hash);

        return [
            'success' => true,
            'code' => 0,
            'message' => '设备绑定成功',
            'data' => [
                'device_id' => $device->id,
                'is_new' => true,
            ],
        ];
    }

    public static function unbindDevice(int $cardId, int $deviceId): bool
    {
        $device = Device::where('id', $deviceId)
            ->where('card_id', $cardId)
            ->find();

        if (!$device) {
            return false;
        }

        $card = Card::find($cardId);
        if ($card) {
            self::clearCardCache($card->card_no_hash);
        }

        $device->delete();
        return true;
    }

    public static function activateCard(string $cardNo, int $appId, string $deviceFingerprint, string $deviceName = ''): array
    {
        $cardNoHash = self::hashCardNo($cardNo);
        $card = self::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status == Card::STATUS_BANNED) {
            return [
                'success' => false,
                'code' => 4104,
                'message' => '卡密已封禁',
            ];
        }

        if ($card->status == Card::STATUS_VOIDED) {
            return [
                'success' => false,
                'code' => 4105,
                'message' => '卡密已作废',
            ];
        }

        $app = App::find($card->app_id);
        if (!$app || $app->status != 1) {
            return [
                'success' => false,
                'code' => 4107,
                'message' => '应用已停用',
            ];
        }

        if ($card->status == Card::STATUS_UNUSED) {
            $card->status = Card::STATUS_ACTIVATED;
            $card->activate_time = date('Y-m-d H:i:s');
            if ($card->card_type != Card::TYPE_PERMANENT) {
                $expireTime = time() + $card->duration;
                $card->expire_time = date('Y-m-d H:i:s', $expireTime);
                $card->soft_expire_until = date('Y-m-d H:i:s', $expireTime + 86400 * 7);
            }
            $card->save();
            self::clearCardCache($cardNoHash);
        }

        $bindResult = self::bindDevice($card->id, $deviceFingerprint, $deviceName);
        if (!$bindResult['success']) {
            return $bindResult;
        }

        $remainingDuration = 0;
        if ($card->card_type != Card::TYPE_PERMANENT && $card->expire_time) {
            $remainingDuration = max(0, strtotime($card->expire_time) - time());
        }

        return [
            'success' => true,
            'code' => 0,
            'message' => '激活成功',
            'data' => [
                'card_id' => $card->id,
                'card_type' => $card->card_type,
                'status' => $card->status,
                'expire_time' => $card->expire_time,
                'remaining_duration' => $remainingDuration,
                'device_id' => $bindResult['data']['device_id'],
                'is_new_activate' => $bindResult['data']['is_new'] ?? false,
            ],
        ];
    }

    public static function renewCard(int $cardId, int $duration): array
    {
        $card = Card::find($cardId);
        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status == Card::STATUS_VOIDED) {
            return [
                'success' => false,
                'message' => '作废卡密无法续费',
            ];
        }

        if ($card->card_type == Card::TYPE_PERMANENT) {
            return [
                'success' => false,
                'message' => '永久卡无需续费',
            ];
        }

        $now = time();
        $currentExpire = $card->expire_time ? strtotime($card->expire_time) : $now;
        $baseTime = max($now, $currentExpire);
        $newExpire = $baseTime + $duration;

        $card->duration += $duration;
        $card->expire_time = date('Y-m-d H:i:s', $newExpire);
        $card->soft_expire_until = date('Y-m-d H:i:s', $newExpire + 86400 * 7);

        if ($card->status == Card::STATUS_EXPIRED) {
            $card->status = Card::STATUS_ACTIVATED;
        }

        $card->save();
        self::clearCardCache($card->card_no_hash);

        return [
            'success' => true,
            'code' => 0,
            'message' => '续费成功',
            'data' => [
                'expire_time' => $card->expire_time,
                'duration' => $card->duration,
            ],
        ];
    }

    public static function banCard(int $cardId, string $reason = ''): array
    {
        $card = Card::find($cardId);
        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status == Card::STATUS_VOIDED) {
            return [
                'success' => false,
                'message' => '作废卡密无法封禁',
            ];
        }

        $card->status = Card::STATUS_BANNED;
        $card->ban_reason = $reason;
        $card->save();

        self::clearCardCache($card->card_no_hash);

        Device::where('card_id', $cardId)->update([
            'is_online' => 0,
        ]);

        return [
            'success' => true,
            'code' => 0,
            'message' => '封禁成功',
        ];
    }

    public static function unbanCard(int $cardId): array
    {
        $card = Card::find($cardId);
        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status != Card::STATUS_BANNED) {
            return [
                'success' => false,
                'message' => '卡密未被封禁',
            ];
        }

        if ($card->expire_time && strtotime($card->expire_time) < time()) {
            $card->status = Card::STATUS_EXPIRED;
        } elseif ($card->activate_time) {
            $card->status = Card::STATUS_ACTIVATED;
        } else {
            $card->status = Card::STATUS_UNUSED;
        }

        $card->save();
        self::clearCardCache($card->card_no_hash);

        return [
            'success' => true,
            'code' => 0,
            'message' => '解封成功',
        ];
    }

    public static function voidCard(int $cardId): array
    {
        $card = Card::find($cardId);
        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status == Card::STATUS_VOIDED) {
            return [
                'success' => false,
                'message' => '卡密已作废',
            ];
        }

        $card->status = Card::STATUS_VOIDED;
        $card->save();

        self::clearCardCache($card->card_no_hash);

        Device::where('card_id', $cardId)->delete();

        return [
            'success' => true,
            'code' => 0,
            'message' => '作废成功',
        ];
    }

    public static function checkBruteForce(int $cardId, string $ip): bool
    {
        $key = self::BRUTE_FORCE_PREFIX . $cardId . ':' . $ip;
        $failCount = Cache::get($key, 0);

        if ($failCount >= self::BRUTE_FORCE_LIMIT) {
            return false;
        }

        return true;
    }

    public static function recordBruteForceFail(int $cardId, string $ip): void
    {
        $key = self::BRUTE_FORCE_PREFIX . $cardId . ':' . $ip;
        $failCount = Cache::get($key, 0);
        $failCount++;
        Cache::set($key, $failCount, self::BRUTE_FORCE_WINDOW);

        if ($failCount >= self::BRUTE_FORCE_LIMIT) {
            self::banCard($cardId, '防爆破自动封禁');
        }
    }

    public static function clearBruteForce(int $cardId, string $ip): void
    {
        $key = self::BRUTE_FORCE_PREFIX . $cardId . ':' . $ip;
        Cache::delete($key);
    }

    public static function heartbeat(string $cardNo, int $appId, string $deviceFingerprint): array
    {
        $cardNoHash = self::hashCardNo($cardNo);
        $card = self::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status != Card::STATUS_ACTIVATED) {
            return [
                'success' => false,
                'message' => '卡密未激活',
            ];
        }

        $device = Device::where('card_id', $card->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->find();

        if (!$device) {
            return [
                'success' => false,
                'message' => '设备未绑定',
            ];
        }

        $device->last_heartbeat = date('Y-m-d H:i:s');
        $device->is_online = 1;
        $device->save();

        return [
            'success' => true,
            'code' => 0,
            'message' => '心跳成功',
            'data' => [
                'expire_time' => $card->expire_time,
                'server_time' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public static function getOnlineCount(int $appId): int
    {
        return Device::where('app_id', $appId)
            ->where('is_online', 1)
            ->where('last_heartbeat', '>=', date('Y-m-d H:i:s', time() - 180))
            ->count();
    }

    public static function importCards(int $appId, int $merchantId, array $cardNos, array $params): array
    {
        $cardType = intval($params['card_type'] ?? 3);
        $duration = intval($params['duration'] ?? 0);
        $createdBy = intval($params['created_by'] ?? 0);

        if (empty($cardNos)) {
            throw new \InvalidArgumentException('卡密列表不能为空');
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $successCount = 0;
            $failCount = 0;
            $failedCards = [];

            foreach ($cardNos as $cardNo) {
                $cardNo = trim($cardNo);
                if (empty($cardNo)) {
                    continue;
                }

                $cardNoHash = self::hashCardNo($cardNo);

                $exists = Card::where('card_no_hash', $cardNoHash)->find();
                if ($exists) {
                    $failCount++;
                    $failedCards[] = $cardNo;
                    continue;
                }

                $prefix = '';
                if (isset($params['prefix']) && $params['prefix']) {
                    $prefix = $params['prefix'];
                } else {
                    $prefix = self::extractPrefix($cardNo);
                }

                $card = new Card();
                $card->app_id = $appId;
                $card->merchant_id = $merchantId;
                $card->card_no_hash = $cardNoHash;
                $card->card_no_prefix = $prefix;
                $card->card_type = $cardType;
                $card->duration = $duration;
                $card->status = Card::STATUS_UNUSED;
                $card->created_by = $createdBy;
                $card->created_at = $now;
                $card->updated_at = $now;
                $card->save();

                $successCount++;
            }

            Db::commit();

            return [
                'success' => true,
                'total' => count($cardNos),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'failed_cards' => $failedCards,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected static function extractPrefix(string $cardNo): string
    {
        $prefix = '';
        $length = strlen($cardNo);
        for ($i = 0; $i < $length; $i++) {
            $char = $cardNo[$i];
            if (ctype_alnum($char) && ctype_upper($char)) {
                $prefix .= $char;
            } elseif ($char == '-') {
                $prefix .= $char;
            } else {
                break;
            }
        }
        return rtrim($prefix, '-');
    }

    public static function rebindDevice(string $cardNo, int $appId, string $oldDevice, string $newDevice, string $deviceName = ''): array
    {
        $cardNoHash = self::hashCardNo($cardNo);
        $card = self::getCardByHash($cardNoHash, $appId);

        if (!$card) {
            return [
                'success' => false,
                'code' => 4101,
                'message' => '卡密不存在',
            ];
        }

        if ($card->status != Card::STATUS_ACTIVATED) {
            return [
                'success' => false,
                'code' => 4102,
                'message' => '卡密未激活',
            ];
        }

        $oldDev = Device::where('card_id', $card->id)
            ->where('device_fingerprint', $oldDevice)
            ->find();

        if (!$oldDev) {
            return [
                'success' => false,
                'message' => '旧设备不存在',
            ];
        }

        $newDevExists = Device::where('card_id', $card->id)
            ->where('device_fingerprint', $newDevice)
            ->find();

        if ($newDevExists) {
            return [
                'success' => true,
                'code' => 0,
                'message' => '新设备已绑定',
                'data' => [
                    'device_id' => $newDevExists->id,
                ],
            ];
        }

        $oldDev->device_fingerprint = $newDevice;
        $oldDev->device_name = $deviceName ?: $oldDev->device_name;
        $oldDev->last_heartbeat = date('Y-m-d H:i:s');
        $oldDev->is_online = 1;
        $oldDev->save();

        self::clearCardCache($cardNoHash);

        return [
            'success' => true,
            'code' => 0,
            'message' => '换绑成功',
            'data' => [
                'device_id' => $oldDev->id,
            ],
        ];
    }
}
