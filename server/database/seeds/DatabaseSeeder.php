<?php

use think\migration\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('UserSeeder');
        $this->call('PackageSeeder');
        $this->call('SystemConfigSeeder');
        $this->call('AdminMenuSeeder');
    }
}
