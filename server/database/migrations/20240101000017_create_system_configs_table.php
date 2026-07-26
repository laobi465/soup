<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSystemConfigsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_system_configs', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '系统配置表',
        ]);

        $table->addColumn('config_key', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '配置键',
        ])
        ->addColumn('config_value', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '配置值',
        ])
        ->addColumn('config_type', 'string', [
            'limit'   => 30,
            'null'    => false,
            'default' => 'string',
            'comment' => '值类型：string int json bool',
        ])
        ->addColumn('group_name', 'string', [
            'limit'   => 50,
            'null'    => false,
            'default' => 'default',
            'comment' => '配置分组',
        ])
        ->addColumn('remark', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '备注说明',
        ])
        ->addColumn('sort', 'integer', [
            'null'    => false,
            'default' => 0,
            'comment' => '排序',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1启用0禁用',
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
        ->addIndex(['config_key'], [
            'unique' => true,
            'name'   => 'uk_config_key',
        ])
        ->addIndex(['group_name'], [
            'name' => 'idx_group_name',
        ])
        ->create();
    }
}
