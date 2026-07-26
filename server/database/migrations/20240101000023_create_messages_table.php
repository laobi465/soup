<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMessagesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_messages', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '消息通知表',
        ]);

        $table->addColumn('user_id', 'integer', [
            'null'    => false,
            'signed'  => false,
            'comment' => '接收用户ID',
        ])
        ->addColumn('title', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '消息标题',
        ])
        ->addColumn('content', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '消息内容',
        ])
        ->addColumn('type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '消息类型：1系统通知 2套餐提醒 3卡密提醒 4提现通知 5工单通知 6异常告警',
        ])
        ->addColumn('is_read', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '是否已读：0未读 1已读',
        ])
        ->addColumn('read_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '读取时间',
        ])
        ->addColumn('sender_id', 'integer', [
            'null'    => true,
            'default' => null,
            'signed'  => false,
            'comment' => '发送者ID，0为系统',
        ])
        ->addColumn('extra', 'json', [
            'null'    => true,
            'default' => null,
            'comment' => '扩展数据',
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
        ->addIndex(['user_id', 'is_read'], [
            'name' => 'idx_user_read',
        ])
        ->addIndex(['user_id', 'created_at'], [
            'name' => 'idx_user_created',
        ])
        ->addIndex(['type'], [
            'name' => 'idx_type',
        ])
        ->create();
    }
}
