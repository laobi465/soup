<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSubRolesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_sub_roles', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '子账号角色表',
        ]);

        $table->addColumn('merchant_id', 'integer', [
            'null'    => false,
            'signed'  => false,
            'comment' => '商户ID',
        ])
        ->addColumn('name', 'string', [
            'limit'   => 50,
            'null'    => false,
            'comment' => '角色名称',
        ])
        ->addColumn('description', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '角色描述',
        ])
        ->addColumn('permissions', 'json', [
            'null'    => true,
            'default' => null,
            'comment' => '权限配置',
        ])
        ->addColumn('sort', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '排序',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：0禁用 1启用',
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
        ->addIndex(['merchant_id'], [
            'name' => 'idx_merchant_id',
        ])
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->create();
    }
}
