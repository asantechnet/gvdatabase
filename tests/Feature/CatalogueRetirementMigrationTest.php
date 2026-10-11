<?php

namespace Tests\Feature;

use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Income;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class CatalogueRetirementMigrationTest extends TestCase
{
    use CreatesBuiltInContracts, DatabaseTruncation;

    private const TABLES = ['services', 'service_variants', 'packages', 'package_types', 'package_service', 'customer_packages', 'scholarships', 'test_offers', 'task_service', 'contract_template_services'];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    public function test_fresh_schema_and_cleanup_down_never_create_catalogue_storage(): void
    {
        $migration = require database_path('migrations/2026_10_08_000010_retire_service_package_catalogue.php');
        $migration->down();
        $leads = require database_path('migrations/2026_10_07_000004_drop_leads_table.php');
        $leads->down();
        $this->assertFalse(Schema::hasTable('leads'));
        $migration->up();
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        foreach (['service_id', 'service_variant_id', 'customer_package_id', 'country_id', 'education_level_id', 'language_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('applicant_contract', $column), $column);
        }
        $this->assertFalse(Schema::hasColumn('contracts', 'source_service_id'));
        $this->assertFalse(Schema::hasColumn('contract_templates', 'service_id'));
        $this->assertFalse(Schema::hasColumn('contract_templates', 'package_id'));
        $this->assertFalse(Schema::hasColumn('contract_document_designs', 'service_id'));
        foreach (['services', 'services/1', 'services/1/variants', 'packages', 'packages/1', 'educational-master-data/package-types'] as $path) {
            $this->getJson('/api/'.$path)->assertNotFound();
            $this->postJson('/api/'.$path, [])->assertNotFound();
        }
    }

    public function test_populated_cleanup_preserves_builtin_work_and_discards_catalogue_links_and_grants(): void
    {
        $created = $this->createBuiltInContract('academy', ['currency_id' => Currency::factory()->create(['is_base' => true, 'rate' => 1])->id]);
        $contract = Contract::findOrFail($created['id']);
        $line = ApplicantContract::where('contract_id', $contract->id)->firstOrFail();
        $payment = $this->postJson('/api/incomes', [
            'applicant_contract_id' => $line->id, 'applicant_id' => $line->applicant_id,
            'type' => 'Service', 'description' => 'Current payment', 'amount' => 25, 'quantity' => 1,
            'currency_id' => $line->currency_id, 'rate' => 1, 'date' => now()->toDateString(),
        ])->assertCreated()->json('data');
        $income = Income::findOrFail($payment['id']);
        $content = $contract->content;
        foreach (self::TABLES as $table) {
            Schema::create($table, fn (Blueprint $blueprint) => $blueprint->id());
            DB::table($table)->insert(['id' => 1]);
        }
        foreach (['contracts' => ['source_service_id'], 'contract_templates' => ['service_id', 'package_id'], 'applicant_contract' => ['service_id', 'service_variant_id', 'customer_package_id', 'country_id', 'education_level_id', 'language_id'], 'contract_document_designs' => ['service_id']] as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->unsignedBigInteger($column)->nullable()->index();
                }
            });
        }
        Schema::table('applicant_contract', function (Blueprint $blueprint): void {
            $blueprint->foreign('service_id')->references('id')->on('services');
            $blueprint->unique(['contract_id', 'service_id']);
        });
        DB::table('applicant_contract')->where('id', $line->id)->update(['service_id' => 1, 'customer_package_id' => 1]);
        $snapshot = $contract->snapshot_data;
        $snapshot['entity']['package_id'] = 1;
        $contract->update(['snapshot_data' => $snapshot]);
        $retired = Notification::create(['notification_key' => 'package.issued.1', 'go_to_url' => '/packages/1', 'date_time' => now()]);
        $retired->deliverTo([auth()->id()]);
        $current = Notification::create(['notification_key' => 'contract.generated.current', 'go_to_url' => '/contracts/academy', 'date_time' => now()]);
        $names = ['view_services', 'manage_services', 'view_packages', 'manage_packages', 'view_package_type', 'create_package_type', 'edit_package_type', 'delete_package_type', 'view_applicant_services', 'view_leads', 'create_leads', 'edit_leads', 'delete_leads', 'convert_leads'];
        $role = Role::where('name', 'staff')->firstOrFail();
        foreach ($names as $name) {
            $permission = Permission::create(['name' => $name, 'category' => 'educational_visa']);
            $role->permissions()->attach($permission->id);
        }
        $ids = Permission::whereIn('name', $names)->pluck('id');
        $migration = require database_path('migrations/2026_10_08_000010_retire_service_package_catalogue.php');
        $migration->up();
        $migration->up();
        $migration->down();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertSame(0, Permission::withTrashed()->whereIn('name', $names)->count());
        $this->assertSame(0, DB::table('role_permissions')->whereIn('permission_id', $ids)->count());
        $this->assertNull($retired->fresh());
        $this->assertSame(0, DB::table('notification_user')->where('notification_id', $retired->id)->count());
        $this->assertNotNull($current->fresh());
        $this->assertSame($content, $contract->fresh()->content);
        $this->assertArrayNotHasKey('package_id', $contract->fresh()->snapshot_data['entity']);
        $this->assertArrayHasKey('our_company', $contract->fresh()->snapshot_data['parties']);
        $this->assertEquals(25, $line->fresh()->amount_paid);
        $this->assertSame($income->receipt_number, $income->fresh()->receipt_number);
        $this->assertDatabaseHas('tasks', ['applicant_contract_id' => $line->id]);
        $this->getJson('/api/incomes/'.$income->id.'/receipt')->assertOk()->assertJsonMissingPath('data.packages');
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }
}
