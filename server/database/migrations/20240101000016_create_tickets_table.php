<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateTicketsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_tickets', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '工单表',
        ]);

        $table->addColumn('ticket_no', 'string', [
            'limit'   => 32,
            'null'    => false,
            'comment' => '工单号',
        ])
        ->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '用户ID',
        ])
        ->addColumn('user_type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '用户类型',
        ])
        ->addColumn('title', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '工单标题',
        ])
        ->addColumn('content', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '工单内容',
        ])
        ->addColumn('priority', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 2,
            'signed'  => false,
            'comment' => '优先级：1低2中3高',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1待处理2处理中3已解决4已关闭',
        ])
        ->addColumn('handler_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '处理人ID',
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
        ->addIndex(['ticket_no'], [
            'unique' => true,
            'name'   => 'uk_ticket_no',
        ])
        ->addIndex(['user_id'], [
            'name' => 'idx_user_id',
        ])
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->create();
    }
}
