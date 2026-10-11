<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'override_contract_payments', 'category' => 'finance', 'guard_name' => 'api',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('name', 'override_contract_payments')->whereNull('deleted_at')->value('id');
        if (! $permissionId) {
            return;
        }
        foreach (DB::table('roles')->whereIn('name', ['admin', 'finance_officer'])->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $permissionId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Permission grants are configuration: retain any subsequent custom grants.
    }
};
