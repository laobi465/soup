<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateWithdrawsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_withdraws', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '提现表',
        ]);

        $table->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '用户ID',
        ])
        ->addColumn('wallet_type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '钱包类型：1商户2代理',
        ])
        ->addColumn('amount', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '提现金额',
        ])
        ->addColumn('fee', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '手续费',
        ])
        ->addColumn('actual_amount', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '实际到账金额',
        ])
        ->addColumn('account', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '收款账户',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1待审核2审核通过3已驳回4处理中5已完成',
        ])
        ->addColumn('audit_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '审核时间',
        ])
        ->addColumn('audit_remark', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '审核备注',
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
        ->addIndex(['user_id'], [
            'name' => 'idx_user_id',
        ])
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->addIndex(['created_at'], [
            'name' => 'idx_created_at',
        ])
        ->create();
    }
}
