<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddMissingFieldsToCards extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_cards');

        if (!$table->hasColumn('sold_time')) {
            $table->addColumn('sold_time', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => '售出时间',
                'after'   => 'soft_expire_until',
            ]);
        }

        if (!$table->hasColumn('order_id')) {
            $table->addColumn('order_id', 'biginteger', [
                'null'    => true,
                'default' => 0,
                'signed'  => false,
                'comment' => '关联订单ID',
                'after'   => 'sold_time',
            ]);
        }

        if (!$table->hasIndex('idx_order_id')) {
            $table->addIndex(['order_id'], [
                'name' => 'idx_order_id',
            ]);
        }

        $table->update();
    }
}
