<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'assign_admission_employee',
            'category' => 'educational_visa',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'assign_admission_employee')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        foreach (DB::table('roles')->whereIn('name', ['admin', 'manager'])->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep permission configuration and any later custom grants intact.
    }
};
