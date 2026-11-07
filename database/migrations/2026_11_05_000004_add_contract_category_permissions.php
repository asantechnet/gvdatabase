<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'view_private_contracts' => 'customer_contracts',
        'view_scholarship_contracts' => 'customer_contracts',
        'view_test_offer_contracts' => 'customer_contracts',
        'view_academy_contracts' => 'customer_contracts',
        'view_employment_contracts' => 'customer_contracts',
        'view_other_contracts' => 'customer_contracts',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name => $category) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'category' => $category,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->whereNull('deleted_at')
            ->pluck('id', 'name');

        // Every employee role gets the five non-employment categories. The
        // viewer role is read-only but is still an employee role, so it also
        // needs the umbrella `view_contracts` gate to use the category pages.
        $regularRoleIds = DB::table('roles')
            ->whereNotIn('name', ['applicant', 'super_admin'])
            ->pluck('id');

        // Regular employees keep the five non-employment categories. This is
        // intentionally wider than the employment gate, which stays with
        // supervisors and administrators.
        $regularCategories = [
            'view_private_contracts',
            'view_scholarship_contracts',
            'view_test_offer_contracts',
            'view_academy_contracts',
            'view_other_contracts',
        ];

        $rows = [];
        foreach ($regularRoleIds as $roleId) {
            if (isset($permissionIds['view_contracts'])) {
                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds['view_contracts'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach ($regularCategories as $name) {
                if (! isset($permissionIds[$name])) {
                    continue;
                }
                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$name],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $supervisoryRoleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'manager', 'team_lead'])
            ->pluck('id');
        foreach ($supervisoryRoleIds as $roleId) {
            if (! isset($permissionIds['view_employment_contracts'])) {
                continue;
            }
            $rows[] = [
                'role_id' => $roleId,
                'permission_id' => $permissionIds['view_employment_contracts'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
