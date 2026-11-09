<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\RoleHierarchy;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * System roles and their canonical seniority. Higher levels outrank lower
     * ones. Levels already customised in the database are preserved, so an
     * administrator's `manage_role_hierarchy` change survives a re-seed.
     */
    public function run(): void
    {
        foreach (RoleHierarchy::LEVELS as $name => $level) {
            $role = Role::query()->firstOrCreate(['name' => $name]);
            if ((int) $role->level === 0) {
                $role->update(['level' => $level]);
            }
            if ($role->is_senior === false && $level >= 220) {
                $role->update(['is_senior' => true]);
            }
        }
    }
}
