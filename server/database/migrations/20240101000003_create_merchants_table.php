<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMerchantsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_merchants', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '商户表',
        ]);

        $table->addColumn('user_id', 'biginteger', [
            'null'    => false,
            'signed'  => false,
            'comment' => '关联用户ID',
        ])
        ->addColumn('merchant_no', 'string', [
            'limit'   => 32,
            'null'    => false,
            'comment' => '商户编号',
        ])
        ->addColumn('merchant_name', 'string', [
            'limit'   => 100,
            'null'    => false,
            'comment' => '商户名称',
        ])
        ->addColumn('package_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '套餐ID',
        ])
        ->addColumn('package_expire', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '套餐到期时间',
        ])
        ->addColumn('balance', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '余额',
        ])
        ->addColumn('frozen_balance', 'decimal', [
            'precision' => 10,
            'scale'     => 2,
            'null'      => false,
            'default'   => '0.00',
            'comment'   => '冻结余额',
        ])
        ->addColumn('app_quota', 'integer', [
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '应用配额',
        ])
        ->addColumn('card_quota', 'biginteger', [
            'null'    => false,
            'default' => 1000,
            'signed'  => false,
            'comment' => '卡密配额',
        ])
        ->addColumn('card_used', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '已用卡密数',
        ])
        ->addColumn('agent_invite_code', 'string', [
            'limit'   => 32,
            'null'    => true,
            'default' => null,
            'comment' => '代理邀请码',
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
        ->addIndex(['user_id'], [
            'unique' => true,
            'name'   => 'uk_user_id',
        ])
        ->addIndex(['merchant_no'], [
            'unique' => true,
            'name'   => 'uk_merchant_no',
        ])
        ->addIndex(['agent_invite_code'], [
            'unique' => true,
            'name'   => 'uk_agent_invite_code',
        ])
        ->create();
    }
}
