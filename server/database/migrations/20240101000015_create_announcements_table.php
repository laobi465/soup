<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAnnouncementsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_announcements', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '公告表',
        ]);

        $table->addColumn('title', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '公告标题',
        ])
        ->addColumn('content', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '公告内容',
        ])
        ->addColumn('type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '公告类型：1系统2活动3维护',
        ])
        ->addColumn('effective_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '生效时间',
        ])
        ->addColumn('expire_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '过期时间',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1启用0禁用',
        ])
        ->addColumn('created_by', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '创建人ID',
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
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->addIndex(['type'], [
            'name' => 'idx_type',
        ])
        ->addIndex(['created_at'], [
            'name' => 'idx_created_at',
        ])
        ->create();
    }
}
