<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDevicesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_devices', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '设备绑定表',
        ]);

        $table->addColumn('card_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '卡密ID',
        ])
        ->addColumn('device_fingerprint', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '设备指纹',
        ])
        ->addColumn('device_name', 'string', [
            'limit'   => 100,
            'null'    => true,
            'default' => null,
            'comment' => '设备名称',
        ])
        ->addColumn('bind_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '绑定时间',
        ])
        ->addColumn('last_heartbeat', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '最后心跳时间',
        ])
        ->addColumn('is_online', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '是否在线：1是0否',
        ])
        ->addIndex(['card_id'], [
            'name' => 'idx_card_id',
        ])
        ->addIndex(['device_fingerprint'], [
            'name' => 'idx_device_fingerprint',
        ])
        ->create();
    }
}
