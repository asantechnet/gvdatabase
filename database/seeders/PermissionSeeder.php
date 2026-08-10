<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'view_users', 'category' => 'user_management'],
            ['name' => 'create_users', 'category' => 'user_management'],
            ['name' => 'edit_users', 'category' => 'user_management'],
            ['name' => 'delete_users', 'category' => 'user_management'],
            ['name' => 'view_roles', 'category' => 'role_management'],
            ['name' => 'create_roles', 'category' => 'role_management'],
            ['name' => 'edit_roles', 'category' => 'role_management'],
            ['name' => 'delete_roles', 'category' => 'role_management'],
            ['name' => 'view_permissions', 'category' => 'permission_management'],
            ['name' => 'assign_permissions', 'category' => 'permission_management'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }
    }
}
