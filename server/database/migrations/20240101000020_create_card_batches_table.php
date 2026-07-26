<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCardBatchesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_card_batches', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '卡密批次表',
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
        ->addColumn('batch_no', 'string', [
            'limit'   => 32,
            'null'    => false,
            'comment' => '批次号',
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
        ->addColumn('count', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '生成数量',
        ])
        ->addColumn('prefix', 'string', [
            'limit'   => 20,
            'null'    => true,
            'default' => null,
            'comment' => '卡密前缀',
        ])
        ->addColumn('length', 'integer', [
            'null'    => false,
            'default' => 16,
            'signed'  => false,
            'comment' => '卡密长度',
        ])
        ->addColumn('remark', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '备注',
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
        ->addIndex(['app_id'], [
            'name' => 'idx_app_id',
        ])
        ->addIndex(['merchant_id'], [
            'name' => 'idx_merchant_id',
        ])
        ->addIndex(['batch_no'], [
            'unique' => true,
            'name'   => 'uk_batch_no',
        ])
        ->create();
    }
}
