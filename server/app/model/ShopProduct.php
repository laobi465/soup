<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class ShopProduct extends Model
{
    protected $name = 'shop_products';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    const STATUS_OFFLINE = 0;
    const STATUS_ONLINE = 1;

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '已下架',
            1 => '上架中',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    public function scopeMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    public function scopeCategory($query, $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function isOnline(): bool
    {
        return $this->status == self::STATUS_ONLINE;
    }

    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }
}
