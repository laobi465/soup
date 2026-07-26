<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Message extends Model
{
    protected $name = 'messages';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $json = ['extra'];
    protected $jsonAssoc = true;

    const TYPE_SYSTEM = 1;
    const TYPE_PACKAGE = 2;
    const TYPE_CARD = 3;
    const TYPE_WITHDRAW = 4;
    const TYPE_TICKET = 5;
    const TYPE_ALERT = 6;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            1 => '系统通知',
            2 => '套餐提醒',
            3 => '卡密提醒',
            4 => '提现通知',
            5 => '工单通知',
            6 => '异常告警',
        ];
        return $types[$data['type']] ?? '未知';
    }

    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', 0);
    }

    public function scopeType($query, $type)
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        }
        return $query->where('type', $type);
    }

    public function isRead(): bool
    {
        return $this->is_read == 1;
    }
}
