<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePackagesTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_packages', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '套餐表',
        ]);

        $table->addColumn('name', 'string', [
            'limit'   => 50,
            'null'    => false,
            'comment' => '套餐名称',
        ])
        ->addColumn('price_month', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '月付价格',
        ])
        ->addColumn('price_quarter', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '季付价格',
        ])
        ->addColumn('price_year', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '年付价格',
        ])
        ->addColumn('app_limit', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '应用数量限制，0为不限',
        ])
        ->addColumn('card_limit', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '卡密数量限制，0为不限',
        ])
        ->addColumn('api_limit_day', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '每日API调用限制，0为不限',
        ])
        ->addColumn('online_limit', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '在线设备数量限制，0为不限',
        ])
        ->addColumn('sub_account_limit', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '子账号数量限制，0为不限',
        ])
        ->addColumn('features', 'json', [
            'null'    => true,
            'default' => null,
            'comment' => '功能特性列表',
        ])
        ->addColumn('sort', 'integer', [
            'null'    => false,
            'default' => 0,
            'comment' => '排序',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1启用0禁用',
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
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->addIndex(['sort'], [
            'name' => 'idx_sort',
        ])
        ->create();
    }
}
