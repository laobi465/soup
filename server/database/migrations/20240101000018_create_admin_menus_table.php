<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAdminMenusTable extends Migrator
{
    public function change()
    {
        $table = $this->table('ca_admin_menus', [
            'engine'    => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment'   => '后台菜单表',
        ]);

        $table->addColumn('parent_id', 'biginteger', [
            'null'    => false,
            'default' => 0,
            'signed'  => false,
            'comment' => '父级菜单ID',
        ])
        ->addColumn('name', 'string', [
            'limit'   => 50,
            'null'    => false,
            'comment' => '菜单名称',
        ])
        ->addColumn('icon', 'string', [
            'limit'   => 100,
            'null'    => true,
            'default' => null,
            'comment' => '菜单图标',
        ])
        ->addColumn('path', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '路由路径',
        ])
        ->addColumn('component', 'string', [
            'limit'   => 255,
            'null'    => true,
            'default' => null,
            'comment' => '组件路径',
        ])
        ->addColumn('permission', 'string', [
            'limit'   => 100,
            'null'    => true,
            'default' => null,
            'comment' => '权限标识',
        ])
        ->addColumn('menu_type', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '菜单类型：1目录2菜单3按钮',
        ])
        ->addColumn('sort', 'integer', [
            'null'    => false,
            'default' => 0,
            'comment' => '排序',
        ])
        ->addColumn('visible', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '是否显示：1显示0隐藏',
        ])
        ->addColumn('status', 'integer', [
            'limit'   => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null'    => false,
            'default' => 1,
            'signed'  => false,
            'comment' => '状态：1启用0禁用',
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
        ->addIndex(['parent_id'], [
            'name' => 'idx_parent_id',
        ])
        ->addIndex(['permission'], [
            'name' => 'idx_permission',
        ])
        ->create();
    }
}
