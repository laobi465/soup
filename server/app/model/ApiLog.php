<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class ApiLog extends Model
{
    protected $name = 'api_logs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }
}
