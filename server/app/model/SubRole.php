<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class SubRole extends Model
{
    protected $name = 'sub_roles';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $json = ['permissions'];
    protected $jsonAssoc = true;

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function scopeMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }
}
