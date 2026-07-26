<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAgentsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_agents', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '分销代理表',
        ]);

        $table->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '用户ID',
        ])
        ->addColumn('merchant_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '根商户ID',
        ])
        ->addColumn('parent_agent_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '上级代理ID',
        ])
        ->addColumn('level', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '代理等级：1一级2二级3三级',
        ])
        ->addColumn('invite_code', 'string', [
            'limit'   => 32,
            'null'    => false,
            'comment' => '邀请码',
        ])
        ->addColumn('invite_url', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '邀请链接',
        ])
        ->addColumn('purchase_price_rate', 'decimal', [
            'precision' => 3,
            'scale'     => 2,
            'null'      => false,
            'default'   => '1.00',
            'comment'   => '拿货价折扣率',
        ])
        ->addColumn('commission_rate', 'decimal', [
            'precision' => 3,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.10',
            'comment'   => '佣金比例',
        ])
        ->addColumn('total_earnings', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '累计收益',
        ])
        ->addColumn('available_balance', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '可用余额',
        ])
        ->addColumn('frozen_balance', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '冻结余额',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1正常0禁用',
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
        ->addIndex(['invite_code'], [
            'unique' => true,
            'name'   => 'uk_invite_code',
        ])
        ->addIndex(['merchant_id', 'level'], [
            'name' => 'idx_merchant_level',
        ])
        ->addIndex(['parent_agent_id'], [
            'name' => 'idx_parent_agent_id',
        ])
        ->create();
    }
}
