<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Card extends Model
{
    protected $name = 'cards';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $hidden = ['card_no_hash'];

    const STATUS_UNUSED = 1;
    const STATUS_ACTIVATED = 2;
    const STATUS_EXPIRED = 3;
    const STATUS_BANNED = 4;
    const STATUS_VOIDED = 5;
    const STATUS_SOLD = 6;

    const TYPE_DAY = 1;
    const TYPE_WEEK = 2;
    const TYPE_MONTH = 3;
    const TYPE_QUARTER = 4;
    const TYPE_YEAR = 5;
    const TYPE_PERMANENT = 6;
    const TYPE_TRIAL = 7;

    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class, 'card_id', 'id');
    }

    public function batch()
    {
        return $this->belongsTo(CardBatch::class, 'batch_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            1 => '未使用',
            2 => '已激活',
            3 => '已到期',
            4 => '已封禁',
            5 => '已作废',
            6 => '已售出',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    public function getCardTypeTextAttr($value, $data)
    {
        $types = [
            1 => '日卡',
            2 => '周卡',
            3 => '月卡',
            4 => '季卡',
            5 => '年卡',
            6 => '永久卡',
            7 => '试用卡',
        ];
        return $types[$data['card_type']] ?? '未知';
    }

    public function scopeApp($query, $appId)
    {
        return $query->where('app_id', $appId);
    }

    public function scopeMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    public function scopeCardType($query, $cardType)
    {
        return $query->where('card_type', $cardType);
    }

    public function isBanned(): bool
    {
        return $this->status == self::STATUS_BANNED;
    }

    public function isVoided(): bool
    {
        return $this->status == self::STATUS_VOIDED;
    }

    public function isExpired(): bool
    {
        if ($this->card_type == self::TYPE_PERMANENT) {
            return false;
        }
        if (!$this->expire_time) {
            return false;
        }
        return strtotime($this->expire_time) < time();
    }

    public function isSoftExpired(): bool
    {
        if (!$this->isExpired()) {
            return false;
        }
        if (!$this->soft_expire_until) {
            return false;
        }
        return strtotime($this->soft_expire_until) >= time();
    }
}
