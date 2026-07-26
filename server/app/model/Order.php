<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Order extends Model
{
    protected $name = 'orders';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    const STATUS_PENDING = 1;
    const STATUS_PAID = 2;
    const STATUS_CLOSED = 3;
    const STATUS_REFUNDED = 4;

    const TYPE_PACKAGE = 1;
    const TYPE_SHOP = 2;
    const TYPE_RECHARGE = 3;
    const TYPE_RENEW = 4;

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id', 'id');
    }

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            1 => '待支付',
            2 => '已支付',
            3 => '已关闭',
            4 => '已退款',
        ];
        return $statuses[$data['pay_status']] ?? '未知';
    }

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            1 => '套餐购买',
            2 => '发卡商品',
            3 => '余额充值',
            4 => '套餐续费',
        ];
        return $types[$data['type']] ?? '未知';
    }

    public function getPayChannelTextAttr($value, $data)
    {
        $channels = [
            'alipay' => '支付宝',
            'wxpay' => '微信支付',
            'qqpay' => 'QQ钱包',
            'balance' => '余额支付',
        ];
        return $channels[$data['pay_channel']] ?? ($data['pay_channel'] ?? '未知');
    }

    public function scopeMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('pay_status', $status);
        }
        return $query->where('pay_status', $status);
    }

    public function scopeType($query, $type)
    {
        if (is_array($type)) {
            return $query->whereIn('type', $type);
        }
        return $query->where('type', $type);
    }

    public function isPaid(): bool
    {
        return $this->pay_status == self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->pay_status == self::STATUS_PENDING;
    }

    public function isClosed(): bool
    {
        return $this->pay_status == self::STATUS_CLOSED;
    }

    public function isRefunded(): bool
    {
        return $this->pay_status == self::STATUS_REFUNDED;
    }
}
