<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class OperationLog extends Model
{
    protected $name = 'operation_logs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
