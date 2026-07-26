<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateShopProductsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_shop_products', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '发卡商品表',
        ]);

        $table->addColumn('merchant_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '商户ID',
        ])
        ->addColumn('app_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '应用ID',
        ])
        ->addColumn('name', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '商品名称',
        ])
        ->addColumn('image', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '商品图片',
        ])
        ->addColumn('description', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '商品描述',
        ])
        ->addColumn('price', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '商品价格',
        ])
        ->addColumn('stock', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '库存',
        ])
        ->addColumn('category', 'string', [
            'limit'   => 50,
            'null'    => true,
            'default' => null,
            'comment' => '分类',
        ])
        ->addColumn('limit_per_user', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '每用户限购，0不限',
        ])
        ->addColumn('limit_per_ip', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '每IP限购，0不限',
        ])
        ->addColumn('limit_per_device', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '每设备限购，0不限',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1上架0下架',
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
        ->addIndex(['merchant_id'], [
            'name' => 'idx_merchant_id',
        ])
        ->addIndex(['app_id'], [
            'name' => 'idx_app_id',
        ])
        ->addIndex(['category'], [
            'name' => 'idx_category',
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
