<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCardsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_cards', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '卡密表',
        ]);

        $table->addColumn('app_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '应用ID',
        ])
        ->addColumn('merchant_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '商户ID',
        ])
        ->addColumn('card_no_hash', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '卡密哈希',
        ])
        ->addColumn('card_no_prefix', 'string', [
            'limit'   => 20,
            'null'    => true,
            'default' => null,
            'comment' => '卡密前缀',
        ])
        ->addColumn('card_type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '卡密类型：1日2周3月4季5年6永久7试用',
        ])
        ->addColumn('duration', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '时长（秒）',
        ])
        ->addColumn('batch_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '批次ID',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1未用2已激活3到期4封禁5作废6已售',
        ])
        ->addColumn('bind_devices', 'json', [
            'null'    => true,
            'default' => null,
            'comment' => '绑定设备列表',
        ])
        ->addColumn('activate_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '激活时间',
        ])
        ->addColumn('expire_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '到期时间',
        ])
        ->addColumn('soft_expire_until', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '软到期时间',
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
        ->addIndex(['app_id'], [
            'name' => 'idx_app_id',
        ])
        ->addIndex(['merchant_id'], [
            'name' => 'idx_merchant_id',
        ])
        ->addIndex(['batch_id'], [
            'name' => 'idx_batch_id',
        ])
        ->addIndex(['card_no_hash'], [
            'unique' => true,
            'name'   => 'uk_card_no_hash',
        ])
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->create();
    }
}
