<?php

use think\migration\Migrator;

class AddUnbindTimeToDevices extends Migrator
{
    public function up()
    {
        $table = $this->table('ca_devices');
        if (!$table->hasColumn('unbind_time')) {
            $table->addColumn('unbind_time', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'bind_time',
                'comment' => '解绑时间（换绑或作废时写入，保留审计链）',
            ])->update();
        }
    }

    public function down()
    {
        $table = $this->table('ca_devices');
        if ($table->hasColumn('unbind_time')) {
            $table->removeColumn('unbind_time')->update();
        }
    }
}
