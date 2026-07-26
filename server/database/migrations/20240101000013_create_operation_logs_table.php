<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateOperationLogsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_operation_logs', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '操作日志表',
        ]);

        $table->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '操作人ID',
        ])
        ->addColumn('action', 'string', [
            'limit'   => 50,
            'null'    => false,
            'comment' => '操作动作',
        ])
        ->addColumn('target_type', 'string', [
            'limit'   => 30,
            'null'    => true,
            'default' => null,
            'comment' => '目标类型',
        ])
        ->addColumn('target_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '目标ID',
        ])
        ->addColumn('ip', 'string', [
            'limit'   => 45,
            'null'    => true,
            'default' => null,
            'comment' => 'IP地址',
        ])
        ->addColumn('user_agent', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '用户代理',
        ])
        ->addColumn('request_data', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '请求数据',
        ])
        ->addColumn('created_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '创建时间',
        ])
        ->addIndex(['user_id'], [
            'name' => 'idx_user_id',
        ])
        ->addIndex(['action'], [
            'name' => 'idx_action',
        ])
        ->addIndex(['created_at'], [
            'name' => 'idx_created_at',
        ])
        ->create();
    }
}
