<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately no role grants: case country contacts are opt-in for
        // normal employees. Super admins inherit every permission through the
        // existing policy bypass.
        DB::table('permissions')->insertOrIgnore([
            'name' => 'view_case_country_contacts',
            'category' => 'educational_visa',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'view_case_country_contacts')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
