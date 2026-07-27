<?php

use think\migration\Migrator;

class AddTaskTokenToApkInjectTasks extends Migrator
{
    public function up()
    {
        $table = $this->table('ca_apk_inject_tasks');
        if (!$table->hasColumn('task_token')) {
            $table->addColumn('task_token', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'after'   => 'sdk_config',
                'comment' => 'SDK 鉴权任务令牌（替代明文 app_secret 注入到 manifest）',
            ])
            ->addIndex(['task_token'], [
                'name' => 'idx_task_token',
            ])
            ->update();
        }
    }

    public function down()
    {
        $table = $this->table('ca_apk_inject_tasks');
        if ($table->hasColumn('task_token')) {
            $table->removeIndexByName('idx_task_token')
                ->removeColumn('task_token')
                ->update();
        }
    }
}
