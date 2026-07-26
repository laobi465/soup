<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class RiskBlacklist extends Model
{
    protected $name = 'risk_blacklist';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    const TYPE_IP = 1;
    const TYPE_DEVICE = 2;
    const TYPE_PHONE = 3;
    const TYPE_EMAIL = 4;

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            1 => 'IP地址',
            2 => '设备',
            3 => '手机号',
            4 => '邮箱',
        ];
        return $types[$data['type']] ?? '未知';
    }

    public function scopeType($query, $type)
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        }
        return $query->where('type', $type);
    }

    public function isExpired(): bool
    {
        if (!$this->expire_time) {
            return false;
        }
        return strtotime($this->expire_time) < time();
    }
}
