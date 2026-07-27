<?php

use think\migration\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $passwordHash = password_hash('admin123456', PASSWORD_BCRYPT);

        $users = [
            [
                'id'              => 1,
                'username'        => 'admin',
                'password_hash'   => $passwordHash,
                'email'           => 'admin@example.com',
                'phone'           => '13800138000',
                'role_type'       => 1,
                'parent_id'       => 0,
                'avatar'          => '',
                'status'          => 1,
                'login_fail_count' => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];

        // 幂等: 已存在则更新 password_hash/updated_at, 否则插入
        // onConflict 需要 Phinx >= 0.13, think-migration v3 已支持
        $this->table('ca_users')
            ->insert($users)
            ->onConflict(['id'])
            ->replace()
            ->save();
    }
}
