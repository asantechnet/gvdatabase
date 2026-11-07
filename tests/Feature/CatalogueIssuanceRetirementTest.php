<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\EducationalReferenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogueIssuanceRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_endpoints_have_no_routes_redirects_or_side_effects(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $currency = Currency::factory()->create();
        foreach (['scholarships', 'test-offers'] as $scope) {
            $this->getJson('/api/educational-services/'.$scope)->assertNotFound();
            $this->getJson('/api/educational-services/'.$scope.'/1')->assertNotFound();
            $this->postJson('/api/educational-services/'.$scope, [])->assertNotFound();
            $this->putJson('/api/educational-services/'.$scope.'/1', [])->assertNotFound();
            $this->deleteJson('/api/educational-services/'.$scope.'/1')->assertNotFound();
        }
        $this->getJson('/api/educational-services/options')->assertNotFound();
        $this->postJson('/api/packages/1/issue', [])->assertNotFound();
        $this->postJson('/api/packages/1/contract-template', [])->assertNotFound();
        $this->postJson('/api/services/1/contract-template', [])->assertNotFound();
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicant_contract', 0);
        $this->assertFalse(Schema::hasTable('customer_packages'));
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_reference_seeding_is_repeatable_without_issuing_unapproved_terms_or_financial_records(): void
    {
        $before = Contract::count();
        $this->seed(EducationalReferenceSeeder::class);
        $this->seed(EducationalReferenceSeeder::class);
        $this->assertDatabaseCount('countries', 4);
        $this->assertDatabaseCount('education_levels', 3);
        $this->assertDatabaseCount('languages', 3);
        $this->assertDatabaseCount('universities', 3);
        $this->assertDatabaseCount('semesters', 1);
        $this->assertSame($before, Contract::count());
        foreach (['applicants', 'incomes', 'educational_bonus_sources', 'applicant_attachments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_retired_permission_migration_removes_grants_and_seeders_do_not_reintroduce_them(): void
    {
        $names = ['issue_packages', 'view_educational_services', 'manage_educational_services'];
        $role = Role::where('name', 'staff')->firstOrFail();
        foreach ($names as $name) {
            $permission = Permission::create(['name' => $name, 'category' => 'educational_visa']);
            $role->permissions()->attach($permission->id);
        }
        $ids = Permission::whereIn('name', $names)->pluck('id');
        $migration = require database_path('migrations/2026_10_08_000002_retire_catalogue_issuance_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame(0, DB::table('role_permissions')->whereIn('permission_id', $ids)->count());
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->assertSame(0, Permission::withTrashed()->whereIn('name', $names)->count());
        $this->assertDatabaseHas('permissions', ['name' => 'create_contracts', 'deleted_at' => null]);
        $this->assertDatabaseHas('permissions', ['name' => 'override_contract_payments', 'deleted_at' => null]);
    }
}
