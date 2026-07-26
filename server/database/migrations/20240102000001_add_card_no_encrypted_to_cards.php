<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddCardNoEncryptedToCards extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_cards');

        if (!$table->hasColumn('card_no_encrypted')) {
            $table->addColumn('card_no_encrypted', 'string', [
                'limit' => 500,
                'null' => true,
                'default' => null,
                'comment' => '卡密明文AES加密',
                'after' => 'card_no_hash',
            ]);
            $table->update();
        }
    }
}
