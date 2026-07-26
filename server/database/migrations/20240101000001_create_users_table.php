<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUsersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_users', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '用户表',
        ]);

        $table->addColumn('username', 'string', [
            'limit'   => 50,
            'null'    => false,
            'comment' => '用户名',
        ])
        ->addColumn('password_hash', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '密码哈希',
        ])
        ->addColumn('email', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '邮箱',
        ])
        ->addColumn('phone', 'string', [
            'limit'   => 20,
            'null'    => true,
            'default' => null,
            'comment' => '手机号',
        ])
        ->addColumn('role_type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 4,
            'signed'  => false,
            'comment' => '角色类型：1超管2运营3商户4商户子5终端6代理',
        ])
        ->addColumn('parent_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '父级用户ID',
        ])
        ->addColumn('avatar', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '头像',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1正常0禁用2锁定3过期',
        ])
        ->addColumn('login_fail_count', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '登录失败次数',
        ])
        ->addColumn('lock_until', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '锁定截止时间',
        ])
        ->addColumn('last_login_ip', 'string', [
            'limit'   => 45,
            'null'    => true,
            'default' => null,
            'comment' => '最后登录IP',
        ])
        ->addColumn('last_login_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '最后登录时间',
        ])
        ->addColumn('created_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '创建时间',
        ])
        ->addColumn('updated_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '更新时间',
        ])
        ->addIndex(['username'], [
            'unique' => true,
            'name'   => 'uk_username',
        ])
        ->addIndex(['email'], [
            'unique' => true,
            'name'   => 'uk_email',
        ])
        ->addIndex(['parent_id'], [
            'name' => 'idx_parent_id',
        ])
        ->addIndex(['role_type'], [
            'name' => 'idx_role_type',
        ])
        ->create();
    }
}
