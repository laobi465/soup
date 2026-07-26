<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Ticket extends Model
{
    protected $name = 'tickets';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    const STATUS_PENDING = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_RESOLVED = 3;
    const STATUS_CLOSED = 4;

    const PRIORITY_LOW = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_HIGH = 3;

    const USER_TYPE_ADMIN = 1;
    const USER_TYPE_MERCHANT = 2;
    const USER_TYPE_AGENT = 3;

    public function replies()
    {
        return $this->hasMany(TicketReply::class, 'ticket_id', 'id')->order('id', 'asc');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            1 => '待处理',
            2 => '处理中',
            3 => '已解决',
            4 => '已关闭',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    public function getPriorityTextAttr($value, $data)
    {
        $priorities = [
            1 => '低',
            2 => '中',
            3 => '高',
        ];
        return $priorities[$data['priority']] ?? '未知';
    }

    public function getUserTypeTextAttr($value, $data)
    {
        $types = [
            1 => '管理员',
            2 => '商户',
            3 => '代理商',
        ];
        return $types[$data['user_type']] ?? '未知';
    }

    public function scopeStatus($query, $status)
    {
        if ($status && $status !== 'all') {
            if (is_array($status)) {
                return $query->whereIn('status', $status);
            }
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePriority($query, $priority)
    {
        if ($priority && $priority !== 'all') {
            return $query->where('priority', $priority);
        }
        return $query;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    public function canReply(): bool
    {
        return !$this->isClosed();
    }
}
