<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['super_admin', 'admin', 'manager', 'staff', 'viewer', 'applicant'];

        foreach ($roles as $name) {
            Role::query()->firstOrCreate(['name' => $name]);
        }
    }
}
