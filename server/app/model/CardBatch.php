<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class CardBatch extends Model
{
    protected $name = 'card_batches';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function cards()
    {
        return $this->hasMany(Card::class, 'batch_id', 'id');
    }

    public function getCardTypeTextAttr($value, $data)
    {
        $types = [
            1 => '日卡',
            2 => '周卡',
            3 => '月卡',
            4 => '季卡',
            5 => '年卡',
            6 => '永久卡',
            7 => '试用卡',
        ];
        return $types[$data['card_type']] ?? '未知';
    }
}
