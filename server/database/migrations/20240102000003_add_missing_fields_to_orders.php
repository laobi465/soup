<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddMissingFieldsToOrders extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_orders');

        if (!$table->hasColumn('email')) {
            $table->addColumn('email', 'string', [
                'limit'   => 100,
                'null'    => true,
                'default' => null,
                'comment' => '买家邮箱',
                'after'   => 'user_id',
            ]);
        }

        if (!$table->hasColumn('extra')) {
            $table->addColumn('extra', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => '额外信息JSON',
                'after'   => 'amount',
            ]);
        }

        if (!$table->hasColumn('pay_trade_no')) {
            $table->addColumn('pay_trade_no', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'comment' => '支付平台交易号',
                'after'   => 'pay_channel',
            ]);
        }

        if (!$table->hasColumn('refund_reason')) {
            $table->addColumn('refund_reason', 'string', [
                'limit'   => 500,
                'null'    => true,
                'default' => null,
                'comment' => '退款原因',
                'after'   => 'pay_time',
            ]);
        }

        if (!$table->hasColumn('refund_time')) {
            $table->addColumn('refund_time', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => '退款时间',
                'after'   => 'refund_reason',
            ]);
        }

        if (!$table->hasIndex('idx_pay_trade_no')) {
            $table->addIndex(['pay_trade_no'], [
                'name' => 'idx_pay_trade_no',
            ]);
        }

        if (!$table->hasIndex('idx_email')) {
            $table->addIndex(['email'], [
                'name' => 'idx_email',
            ]);
        }

        $table->update();
    }
}
