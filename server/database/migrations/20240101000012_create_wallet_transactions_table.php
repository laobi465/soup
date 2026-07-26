<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateWalletTransactionsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_wallet_transactions', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '钱包流水表',
        ]);

        $table->addColumn('wallet_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '钱包ID',
        ])
        ->addColumn('type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '流水类型：1收入2支出3提现4冻结5解冻',
        ])
        ->addColumn('amount', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '变动金额',
        ])
        ->addColumn('related_order', 'string', [
            'limit'   => 32,
            'null'    => true,
            'default' => null,
            'comment' => '关联订单号',
        ])
        ->addColumn('balance_after', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '变动后余额',
        ])
        ->addColumn('settle_date', 'date', [
            'null'    => true,
            'default' => null,
            'comment' => '结算日期',
        ])
        ->addColumn('settle_status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '结算状态：0待结算1已结算',
        ])
        ->addColumn('remark', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '备注',
        ])
        ->addColumn('created_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '创建时间',
        ])
        ->addIndex(['wallet_id'], [
            'name' => 'idx_wallet_id',
        ])
        ->addIndex(['settle_status'], [
            'name' => 'idx_settle_status',
        ])
        ->addIndex(['created_at'], [
            'name' => 'idx_created_at',
        ])
        ->create();
    }
}
