<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class WalletTransaction extends Model
{
    protected $name = 'wallet_transactions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            1 => '收入',
            2 => '支出',
            3 => '提现',
            4 => '冻结',
            5 => '解冻',
        ];
        return $types[$data['type']] ?? '未知';
    }

    public function getSettleStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '待结算',
            1 => '已结算',
        ];
        return $statuses[$data['settle_status']] ?? '未知';
    }
}
