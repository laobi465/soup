<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddAppSecretEncryptedToAppsTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_apps');

        if (!$table->hasColumn('app_secret_encrypted')) {
            $table->addColumn('app_secret_encrypted', 'text', [
                'null' => true,
                'default' => null,
                'comment' => '应用密钥（AES加密）',
                'after' => 'app_secret_hash',
            ]);
            $table->update();
        }
    }
}
