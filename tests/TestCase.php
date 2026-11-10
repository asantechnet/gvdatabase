<?php

namespace Tests;

use App\Models\Applicant;
use App\Models\ApplicantAttachment;
use App\Support\RoleHierarchy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalog();
    }

    /**
     * Seed the canonical permissions, roles and grants in one batch.
     *
     * The Eloquent seeders issue thousands of SELECT/INSERT pairs per call
     * (about 3,250 queries and 1.9 seconds), and this runs for every test.
     * The catalogue is system-owned data with no model side effects that the
     * suite relies on, so bulk writes are equivalent and keep setup in the
     * low-millisecond range. Seeders remain the source of truth for the data.
     */
    protected function seedAuthorizationCatalog(): void
    {
        $now = now();

        $permissions = array_map(static fn (array $permission): array => [
            'name' => $permission['name'],
            'category' => $permission['category'],
            'guard_name' => 'api',
            'created_at' => $now,
            'updated_at' => $now,
        ], PermissionSeeder::catalog());

        DB::table('permissions')->upsert(
            $permissions,
            ['name'],
            ['category', 'guard_name', 'updated_at'],
        );

        $roles = [];

        foreach (RoleHierarchy::LEVELS as $name => $level) {
            $roles[] = [
                'name' => $name,
                'level' => $level,
                'is_senior' => $level >= 220,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('roles')->insertOrIgnore($roles);

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $rows = [];

        foreach (RolePermissionSeeder::grants() as $roleName => $permissionNames) {
            $roleId = $roleIds[$roleName] ?? null;

            if ($roleId === null) {
                continue;
            }

            foreach (array_unique($permissionNames) as $permissionName) {
                $permissionId = $permissionIds[$permissionName] ?? null;

                if ($permissionId !== null) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::table('role_permissions')->insertOrIgnore($rows);
    }

    /**
     * Create profile documents for tests that exercise the documents feature.
     * Documents are independent of stage changes.
     */
    protected function seedApplicantDocuments(Applicant|int $applicant, int $count = 1): void
    {
        $applicant = $applicant instanceof Applicant ? $applicant : Applicant::findOrFail($applicant);

        for ($i = 1; $i <= $count; $i++) {
            ApplicantAttachment::create([
                'applicant_id' => $applicant->id,
                'label' => "Test document {$i}",
                'category' => 'identity',
                'disk' => 'local',
                'path' => "applicant-attachments/{$applicant->id}/test-doc-{$i}.pdf",
                'original_name' => "test-doc-{$i}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 1024,
            ]);
        }
    }
}
