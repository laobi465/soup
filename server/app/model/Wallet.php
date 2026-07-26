<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Wallet extends Model
{
    protected $name = 'wallets';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id', 'id');
    }

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            1 => '商户钱包',
            2 => '代理钱包',
        ];
        return $types[$data['type']] ?? '未知';
    }
}
