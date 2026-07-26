<?php

use think\migration\Migrator;
use think\migration\db\Column;

class AddUpdatedAtToWalletTransactions extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_wallet_transactions');

        if (!$table->hasColumn('updated_at')) {
            $table->addColumn('updated_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => '更新时间',
                'after'   => 'created_at',
            ]);
        }

        $table->update();
    }
}
