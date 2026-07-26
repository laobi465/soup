<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddBuyerFieldsToOrders extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_orders');

        if (!$table->hasColumn('buyer_ip')) {
            $table->addColumn('buyer_ip', 'string', [
                'limit' => 45,
                'null' => true,
                'default' => null,
                'comment' => '下单IP',
                'after' => 'user_id',
            ]);
        }

        if (!$table->hasColumn('device_id')) {
            $table->addColumn('device_id', 'string', [
                'limit' => 100,
                'null' => true,
                'default' => null,
                'comment' => '设备ID',
                'after' => 'buyer_ip',
            ]);
        }

        if (!$table->hasIndex('idx_buyer_ip')) {
            $table->addIndex(['buyer_ip'], [
                'name' => 'idx_buyer_ip',
            ]);
        }

        $table->update();
    }
}
