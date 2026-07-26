<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class SystemConfig extends Model
{
    protected $name = 'system_configs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function scopeGroup($query, $groupName)
    {
        if (is_array($groupName)) {
            return $query->whereIn('group_name', $groupName);
        }
        return $query->where('group_name', $groupName);
    }

    public function scopeStatus($query, $status = 1)
    {
        return $query->where('status', $status);
    }
}
