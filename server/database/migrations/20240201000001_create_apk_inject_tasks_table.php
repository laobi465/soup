<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateApkInjectTasksTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_apk_inject_tasks', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => 'APK注入任务表',
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
        ->addColumn('task_no', 'string', [
            'limit'   => 32,
            'null'    => false,
            'comment' => '任务编号',
        ])
        ->addColumn('source_path', 'string', [
            'limit'   => 500,
            'null'    => false,
            'comment' => '源APK在MinIO的路径',
        ])
        ->addColumn('output_path', 'string', [
            'limit'   => 500,
            'null'    => true,
            'default' => null,
            'comment' => '注入后APK的MinIO路径',
        ])
        ->addColumn('file_sha256', 'string', [
            'limit'   => 64,
            'null'    => false,
            'comment' => '文件SHA-256',
        ])
        ->addColumn('file_size', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '文件大小(字节)',
        ])
        ->addColumn('original_filename', 'string', [
            'limit'   => 255,
            'null'    => false,
            'comment' => '原始文件名',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态: 1排队 2处理中 3完成 4失败',
        ])
        ->addColumn('progress', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '进度: 0-100',
        ])
        ->addColumn('error_log', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => '错误日志',
        ])
        ->addColumn('sdk_config', 'text', [
            'null'    => true,
            'default' => null,
            'comment' => 'SDK配置JSON',
        ])
        ->addColumn('completed_at', 'datetime', [
            'null'    => true,
            'default' => null,
            'comment' => '完成时间',
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
        ->addIndex(['status'], [
            'name' => 'idx_status',
        ])
        ->addIndex(['file_sha256'], [
            'name' => 'idx_file_sha256',
        ])
        ->addIndex(['task_no'], [
            'name' => 'idx_task_no',
        ])
        ->create();
    }
}
