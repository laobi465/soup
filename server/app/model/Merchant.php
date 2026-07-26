<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Merchant extends Model
{
    protected $name = 'merchants';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '禁用',
            1 => '正常',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    public function isPackageExpired(): bool
    {
        if (empty($this->package_expire)) {
            return false;
        }
        return strtotime($this->package_expire) < time();
    }

    public function getRemainingAppsAttr()
    {
        $package = $this->package;
        if (!$package || $package->app_limit == 0) {
            return -1;
        }
        return max(0, $this->app_quota);
    }

    public function getRemainingCardsAttr()
    {
        $package = $this->package;
        if (!$package || $package->card_limit == 0) {
            return -1;
        }
        return max(0, $this->card_quota - $this->card_used);
    }
}
