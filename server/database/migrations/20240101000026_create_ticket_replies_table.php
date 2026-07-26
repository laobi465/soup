<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateTicketRepliesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_ticket_replies', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '工单回复表',
        ]);

        $table->addColumn('ticket_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '工单ID',
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
            'comment' => '用户类型：1用户2管理员',
        ])
        ->addColumn('content', 'text', [
            'null'    => false,
            'comment' => '回复内容',
        ])
        ->addColumn('attachments', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '附件JSON',
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
        ->addIndex(['ticket_id'], [
            'name' => 'idx_ticket_id',
        ])
        ->addIndex(['user_id'], [
            'name' => 'idx_user_id',
        ])
        ->addIndex(['created_at'], [
            'name' => 'idx_created_at',
        ])
        ->create();
    }
}
