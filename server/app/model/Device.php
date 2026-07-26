<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Device extends Model
{
    protected $name = 'devices';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = false;
    protected $updateTime = false;

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }

    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    public function getIsOnlineTextAttr($value, $data)
    {
        return $data['is_online'] == 1 ? '在线' : '离线';
    }

    public function scopeCard($query, $cardId)
    {
        return $query->where('card_id', $cardId);
    }

    public function scopeFingerprint($query, $fingerprint)
    {
        return $query->where('device_fingerprint', $fingerprint);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', 1);
    }
}
