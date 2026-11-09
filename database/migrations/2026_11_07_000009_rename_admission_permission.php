<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Responsibility is now plural: `assign_responsible_employees`.
 * The grant is renamed in place so every existing role keeps access. New
 * Academy manage grants are added separately and never replace a role grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = DB::table('permissions')->where('name', 'assign_admission_employee')->whereNull('deleted_at')->first();
        $new = DB::table('permissions')->where('name', 'assign_responsible_employees')->whereNull('deleted_at')->first();

        if ($old && ! $new) {
            DB::table('permissions')->where('id', $old->id)->update([
                'name' => 'assign_responsible_employees',
                'updated_at' => now(),
            ]);
            $new = (object) ['id' => $old->id];
        } elseif ($old && $new) {
            // A pre-release database may already contain both names. Merge the
            // grants onto the canonical row before removing the duplicate.
            $grants = DB::table('role_permissions')->where('permission_id', $old->id)->get();
            foreach ($grants as $grant) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $grant->role_id,
                    'permission_id' => $new->id,
                    'created_at' => $grant->created_at ?? now(),
                    'updated_at' => $grant->updated_at ?? now(),
                ]);
            }
            DB::table('role_permissions')->where('permission_id', $old->id)->delete();
            DB::table('permissions')->where('id', $old->id)->delete();
        }

        $new ??= DB::table('permissions')->where('name', 'assign_responsible_employees')->first();

        if (! $new) {
            $id = DB::table('permissions')->insertGetId([
                'name' => 'assign_responsible_employees',
                'category' => 'educational_visa',
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $new = (object) ['id' => $id];
        }

        foreach ([
            ['name' => 'manage_academy_classes', 'category' => 'educational_visa'],
            ['name' => 'manage_academy_attendance', 'category' => 'educational_visa'],
            ['name' => 'issue_academy_certificates', 'category' => 'educational_visa'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'category' => $permission['category'],
                    'guard_name' => 'api',
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $managerRoles = DB::table('roles')->whereIn('name', ['admin', 'manager', 'team_lead'])->pluck('id');
        $academyPermissionIds = DB::table('permissions')
            ->whereIn('name', ['manage_academy_classes', 'manage_academy_attendance', 'issue_academy_certificates'])
            ->pluck('id');
        foreach ($managerRoles as $roleId) {
            foreach ($academyPermissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $new = DB::table('permissions')->where('name', 'assign_responsible_employees')->whereNull('deleted_at')->first();
        $old = DB::table('permissions')->where('name', 'assign_admission_employee')->whereNull('deleted_at')->first();

        if ($new && ! $old) {
            DB::table('permissions')->where('id', $new->id)->update([
                'name' => 'assign_admission_employee',
                'updated_at' => now(),
            ]);
        }
    }
};
