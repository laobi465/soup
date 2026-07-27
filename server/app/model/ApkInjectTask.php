<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class ApkInjectTask extends Model
{
    protected $name = 'apk_inject_tasks';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $json = ['sdk_config'];
    protected $jsonAssoc = true;

    const STATUS_PENDING = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_FAILED = 4;

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
            1 => '排队',
            2 => '处理中',
            3 => '完成',
            4 => '失败',
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

    public function isPending(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status == self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status == self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status == self::STATUS_FAILED;
    }
}
