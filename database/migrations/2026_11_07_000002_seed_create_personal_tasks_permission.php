<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'create_personal_tasks',
            'category' => 'task_management',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'create_personal_tasks')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $now = now();
        $rows = DB::table('roles')
            ->where('name', '!=', 'applicant')
            ->pluck('id')
            ->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        // Permission grants are configuration: retain any later custom grants.
    }
};
