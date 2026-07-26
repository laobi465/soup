<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateOrdersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_orders', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '订单表',
        ]);

        $table->addColumn('order_no', 'string', [
            'limit'   => 32,
            'null'    => false,
            'comment' => '订单号',
        ])
        ->addColumn('type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '订单类型：1套餐2发卡3充值4续费',
        ])
        ->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '用户ID',
        ])
        ->addColumn('merchant_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '商户ID',
        ])
        ->addColumn('product_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '商品ID',
        ])
        ->addColumn('card_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '卡密ID',
        ])
        ->addColumn('amount', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '订单金额',
        ])
        ->addColumn('pay_channel', 'string', [
            'limit'   => 20,
            'null'    => true,
            'default' => null,
            'comment' => '支付渠道',
        ])
        ->addColumn('pay_status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '支付状态：1待付2已付3关闭4退款',
        ])
        ->addColumn('pay_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '支付时间',
        ])
        ->addColumn('expire_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '过期时间',
        ])
        ->addColumn('agent_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '代理ID',
        ])
        ->addColumn('commission_amount', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '佣金金额',
        ])
        ->addColumn('settle_status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '结算状态：0待结算1已结算',
        ])
        ->addColumn('settle_date', 'date', [
            'null'    => true,
            'default' => null,
            'comment' => '结算日期',
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
        ->addIndex(['order_no'], [
            'unique' => true,
            'name'   => 'uk_order_no',
        ])
        ->addIndex(['user_id'], [
            'name' => 'idx_user_id',
        ])
        ->addIndex(['merchant_id'], [
            'name' => 'idx_merchant_id',
        ])
        ->addIndex(['pay_status'], [
            'name' => 'idx_pay_status',
        ])
        ->addIndex(['settle_status'], [
            'name' => 'idx_settle_status',
        ])
        ->create();
    }
}
