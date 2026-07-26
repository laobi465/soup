<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateRiskBlacklistTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_risk_blacklist', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '黑名单表',
        ]);

        $table->addColumn('type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'signed'  => false,
            'comment' => '黑名单类型：1IP2设备3手机4邮箱',
        ])
        ->addColumn('value', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '黑名单值',
        ])
        ->addColumn('reason', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '原因',
        ])
        ->addColumn('expire_time', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '过期时间，null为永久',
        ])
        ->addColumn('created_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '创建时间',
        ])
        ->addIndex(['type', 'value'], [
            'name' => 'idx_type_value',
        ])
        ->addIndex(['expire_time'], [
            'name' => 'idx_expire_time',
        ])
        ->create();
    }
}
