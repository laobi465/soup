<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class App extends Model
{
    protected $name = 'apps';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $hidden = ['app_secret_hash'];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function cards()
    {
        return $this->hasMany(Card::class, 'app_id', 'id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class, 'app_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '停用',
            1 => '启用',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    public function getIpWhitelistAttr($value)
    {
        if (empty($value)) {
            return [];
        }
        $ips = explode("\n", $value);
        return array_values(array_filter(array_map('trim', $ips)));
    }

    public function setIpWhitelistAttr($value)
    {
        if (is_array($value)) {
            return implode("\n", $value);
        }
        return $value;
    }

    public function scopeMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }
}
