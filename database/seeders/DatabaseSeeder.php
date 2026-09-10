<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed only mandatory, idempotent authorization reference data.
     * Operational/demo records must be loaded through explicitly selected
     * seeders and must never be truncated by a production deployment.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
