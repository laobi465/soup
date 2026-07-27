<?php

use think\migration\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $packages = [
            [
                'id'              => 1,
                'name'            => '青铜套餐',
                'price_month'     => 9.90,
                'price_quarter'   => 26.90,
                'price_year'      => 99.00,
                'app_limit'       => 1,
                'card_limit'      => 1000,
                'api_limit_day'   => 10000,
                'online_limit'    => 1,
                'sub_account_limit' => 0,
                'features'        => json_encode(['basic_auth', 'card_manage', 'basic_stats']),
                'sort'            => 1,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => 2,
                'name'            => '白银套餐',
                'price_month'     => 29.90,
                'price_quarter'   => 79.90,
                'price_year'      => 299.00,
                'app_limit'       => 3,
                'card_limit'      => 10000,
                'api_limit_day'   => 100000,
                'online_limit'    => 3,
                'sub_account_limit' => 2,
                'features'        => json_encode(['basic_auth', 'card_manage', 'advanced_stats', 'shop', 'api_access']),
                'sort'            => 2,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => 3,
                'name'            => '黄金套餐',
                'price_month'     => 99.00,
                'price_quarter'   => 269.00,
                'price_year'      => 999.00,
                'app_limit'       => 10,
                'card_limit'      => 100000,
                'api_limit_day'   => 1000000,
                'online_limit'    => 10,
                'sub_account_limit' => 10,
                'features'        => json_encode(['basic_auth', 'card_manage', 'advanced_stats', 'shop', 'api_access', 'agent_system', 'custom_brand']),
                'sort'            => 3,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => 4,
                'name'            => '钻石套餐',
                'price_month'     => 299.00,
                'price_quarter'   => 799.00,
                'price_year'      => 2999.00,
                'app_limit'       => 0,
                'card_limit'      => 1000000,
                'api_limit_day'   => 10000000,
                'online_limit'    => 0,
                'sub_account_limit' => 0,
                'features'        => json_encode(['basic_auth', 'card_manage', 'advanced_stats', 'shop', 'api_access', 'agent_system', 'custom_brand', 'priority_support', 'custom_development', 'data_export']),
                'sort'            => 4,
                'status'          => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];

        // 幂等: 已存在则更新, 否则插入 (与 UserSeeder 一致, 避免重复执行报 Duplicate entry)
        // onConflict 需要 Phinx >= 0.13, think-migration v3 已支持
        $this->table('ca_packages')
            ->insert($packages)
            ->onConflict(['id'])
            ->replace()
            ->save();
    }
}
