<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateApiLogsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_api_logs', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'API调用日志',
        ]);

        $table->addColumn('app_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '应用ID',
        ])
        ->addColumn('card_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '卡密ID',
        ])
        ->addColumn('ip', 'string', [
            'limit'   => 45,
            'null'    => true,
            'default' => null,
            'comment' => 'IP地址',
        ])
        ->addColumn('device', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '设备标识',
        ])
        ->addColumn('api_type', 'string', [
            'limit'   => 30,
            'null'    => false,
            'comment' => 'API类型',
        ])
        ->addColumn('request_data', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '请求数据',
        ])
        ->addColumn('response_code', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '响应码',
        ])
        ->addColumn('cost_ms', 'integer', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '耗时（毫秒）',
        ])
        ->addColumn('created_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '创建时间',
        ])
        ->addIndex(['app_id'], [
            'name' => 'idx_app_id',
        ])
        ->addIndex(['created_at'], [
            'name' => 'idx_created_at',
        ])
        ->addIndex(['api_type'], [
            'name' => 'idx_api_type',
        ])
        ->create();
    }
}
