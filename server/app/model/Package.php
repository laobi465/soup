<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class Package extends Model
{
    protected $name = 'packages';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $json = ['features'];
    protected $jsonAssoc = true;

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '禁用',
            1 => '启用',
        ];
        return $statuses[$data['status']] ?? '未知';
    }
}
