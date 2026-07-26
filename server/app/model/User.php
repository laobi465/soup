<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class User extends Model
{
    protected $name = 'users';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $hidden = ['password_hash'];

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'user_id', 'id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id', 'id');
    }

    public function getRoleTypeTextAttr($value, $data)
    {
        $roles = [
            1 => '超级管理员',
            2 => '运营人员',
            3 => '商户',
            4 => '商户子账号',
            5 => '终端用户',
            6 => '代理商',
        ];
        return $roles[$data['role_type']] ?? '未知';
    }

    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            0 => '禁用',
            1 => '正常',
            2 => '锁定',
            3 => '过期',
        ];
        return $statuses[$data['status']] ?? '未知';
    }
}
