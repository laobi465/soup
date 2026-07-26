<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_apps', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '应用表',
        ]);

        $table->addColumn('merchant_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '商户ID',
        ])
        ->addColumn('name', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '应用名称',
        ])
        ->addColumn('icon', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '应用图标',
        ])
        ->addColumn('version', 'string', [
            'limit'   => 20,
            'null'    => true,
            'default' => null,
            'comment' => '版本号',
        ])
        ->addColumn('description', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '应用描述',
        ])
        ->addColumn('app_key', 'string', [
            'limit'   => 64,
            'null'    => false,
            'comment' => '应用Key',
        ])
        ->addColumn('app_secret_hash', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '应用密钥哈希',
        ])
        ->addColumn('ip_whitelist', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => 'IP白名单',
        ])
        ->addColumn('bind_limit', 'integer', [
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '绑定设备数限制',
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
        ->addIndex(['merchant_id'], [
            'name' => 'idx_merchant_id',
        ])
        ->addIndex(['app_key'], [
            'unique' => true,
            'name'   => 'uk_app_key',
        ])
        ->create();
    }
}
