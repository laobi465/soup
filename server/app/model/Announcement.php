<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Announcement extends Model
{
    protected $name = 'announcements';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    const TYPE_SYSTEM = 1;
    const TYPE_ACTIVITY = 2;
    const TYPE_MAINTENANCE = 3;

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            1 => '系统公告',
            2 => '活动公告',
            3 => '维护公告',
        ];
        return $types[$data['type']] ?? '未知';
    }

    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] == 1 ? '已发布' : '已下架';
    }

    public function scopeType($query, $type)
    {
        if ($type && $type !== 'all') {
            return $query->where('type', $type);
        }
        return $query;
    }

    public function scopeStatus($query, $status = 1)
    {
        return $query->where('status', $status);
    }

    public function scopeEffective($query)
    {
        $now = date('Y-m-d H:i:s');
        return $query->where(function ($q) use ($now) {
            $q->whereNull('effective_time')
                ->whereOr('effective_time', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('expire_time')
                ->whereOr('expire_time', '>=', $now);
        });
    }

    public function isActive(): bool
    {
        $now = time();
        if ($this->effective_time && strtotime($this->effective_time) > $now) {
            return false;
        }
        if ($this->expire_time && strtotime($this->expire_time) < $now) {
            return false;
        }
        return $this->status == 1;
    }
}
