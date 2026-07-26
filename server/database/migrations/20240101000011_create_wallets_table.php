<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateWalletsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_wallets', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '钱包表',
        ]);

        $table->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '用户ID',
        ])
        ->addColumn('type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '钱包类型：1商户2代理',
        ])
        ->addColumn('balance', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '余额',
        ])
        ->addColumn('frozen', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '冻结金额',
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
        ->addIndex(['user_id', 'type'], [
            'unique' => true,
            'name'   => 'uk_user_type',
        ])
        ->create();
    }
}
