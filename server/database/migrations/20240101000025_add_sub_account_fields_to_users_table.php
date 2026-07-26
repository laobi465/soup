<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddSubAccountFieldsToUsersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_users');

        if (!$table->hasColumn('sub_role_id')) {
            $table->addColumn('sub_role_id', 'integer', [
                'null'    => true,
                'default' => null,
                'signed'  => false,
                'after'   => 'role_type',
                'comment' => '子账号角色ID',
            ]);
        }

        if (!$table->hasColumn('app_ids')) {
            $table->addColumn('app_ids', 'json', [
                'null'    => true,
                'default' => null,
                'after'   => 'sub_role_id',
                'comment' => '可访问的应用ID列表',
            ]);
        }

        if (!$table->hasColumn('real_name')) {
            $table->addColumn('real_name', 'string', [
                'limit'   => 50,
                'null'    => true,
                'default' => null,
                'after'   => 'app_ids',
                'comment' => '真实姓名',
            ]);
        }

        if (!$table->hasColumn('merchant_id')) {
            $table->addColumn('merchant_id', 'integer', [
                'null'    => true,
                'default' => null,
                'signed'  => false,
                'after'   => 'real_name',
                'comment' => '所属商户ID（子账号用）',
            ]);

            $table->addIndex(['merchant_id'], [
                'name' => 'idx_merchant_id',
            ]);
        }

        if (!$table->hasIndex('merchant_id')) {
            $table->addIndex(['merchant_id'], [
                'name' => 'idx_merchant_id',
            ]);
        }

        $table->update();
    }
}
