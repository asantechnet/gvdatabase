<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->json('profile_data')->nullable()->after('notes');
            $table->timestamp('profile_completed_at')->nullable()->after('profile_data');
        });

        // Deployments do not always run db:seed. Bootstrap the protected role
        // and its minimum grants here as well, while the seeders keep the same
        // result reproducible for fresh local databases.
        $now = now();
        $role = DB::table('roles')->where('name', 'applicant')->first();
        $roleId = $role?->id ?? DB::table('roles')->insertGetId([
            'name' => 'applicant', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $permissions = [
            'view_own_applicant_portal' => 'applicant_portal',
            'edit_own_applicant_profile' => 'applicant_portal',
            'invite_applicant_portal' => 'educational_visa',
        ];
        foreach ($permissions as $name => $category) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['category' => $category, 'guard_name' => 'api', 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id', 'name');
        foreach (['view_own_applicant_portal', 'edit_own_applicant_profile'] as $permission) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
        foreach (['admin', 'manager', 'staff'] as $roleName) {
            $operationalRoleId = DB::table('roles')->where('name', $roleName)->value('id');
            if ($operationalRoleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $operationalRoleId, 'permission_id' => $permissionIds['invite_applicant_portal']],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['profile_data', 'profile_completed_at']);
        });
    }
};
