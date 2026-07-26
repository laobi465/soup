<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Agent extends Model
{
    protected $name = 'agents';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    const LEVEL_ONE = 1;
    const LEVEL_TWO = 2;
    const LEVEL_THREE = 3;

    const STATUS_DISABLED = 0;
    const STATUS_NORMAL = 1;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function parentAgent()
    {
        return $this->belongsTo(self::class, 'parent_agent_id', 'id');
    }

    public function childAgents()
    {
        return $this->hasMany(self::class, 'parent_agent_id', 'id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id')->where('type', 2);
    }

    public function getLevelTextAttr($value, $data)
    {
        $levels = [
            1 => '一级代理',
            2 => '二级代理',
            3 => '三级代理',
        ];
        return $levels[$data['level']] ?? '未知';
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '禁用',
            1 => '正常',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    public function scopeMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeLevel($query, $level)
    {
        if (is_array($level)) {
            return $query->whereIn('level', $level);
        }
        return $query->where('level', $level);
    }

    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    public function isNormal(): bool
    {
        return $this->status == self::STATUS_NORMAL;
    }

    public function canInvite(): bool
    {
        return $this->level < self::LEVEL_THREE && $this->isNormal();
    }

    public function getAvailableBalance(): string
    {
        return bcsub(strval($this->available_balance), strval($this->frozen_balance), 2);
    }
}
