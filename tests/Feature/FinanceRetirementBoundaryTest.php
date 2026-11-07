<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuditResourceSetting;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Income;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visitor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceRetirementBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_removed_routes_are_not_redirected_or_replaced(): void
    {
        foreach (['companies', 'invoices', 'payment-details', 'outsourcing', 'outsourcing-settlements'] as $resource) {
            $this->getJson('/api/'.$resource)->assertNotFound();
            $this->postJson('/api/'.$resource, [])->assertNotFound();
            $this->putJson('/api/'.$resource.'/1', [])->assertNotFound();
            $this->deleteJson('/api/'.$resource.'/1')->assertNotFound();
        }
        // The contract-driven commission ledger was deliberately restored (see
        // docs/commissions-visitor-relationships-plan.md), so its workspace
        // must answer again instead of 404ing like the retired entities.
        $this->assertNotSame(404, $this->getJson('/api/commissions/summary')->getStatusCode());
        $this->assertNotSame(404, $this->getJson('/api/commission-settlements')->getStatusCode());
        $this->assertDatabaseCount('incomes', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_removed_payment_links_are_rejected_without_creating_or_repricing_income(): void
    {
        $currency = Currency::factory()->create(['rate' => 1, 'is_base' => true]);
        $payload = [
            'type' => 'Other', 'amount' => 25, 'quantity' => 1,
            'currency_id' => $currency->id, 'date' => now()->toDateString(),
        ];
        foreach (['invoice_id', 'invoice_amount', 'company_id'] as $field) {
            $this->postJson('/api/incomes', [...$payload, $field => 999])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('incomes', 0);

        $id = $this->postJson('/api/incomes', $payload)->assertCreated()->json('data.id');
        $before = Income::findOrFail($id)->getAttributes();
        foreach (['invoice_id', 'invoice_amount', 'company_id'] as $field) {
            $this->putJson('/api/incomes/'.$id, [$field => 999, 'amount' => 50])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame($before, Income::findOrFail($id)->getAttributes());
        $this->assertSame(1.0, (float) $currency->fresh()->rate);
    }

    public function test_removed_counterparty_inputs_cannot_be_silently_accepted(): void
    {
        $this->postJson('/api/visitors', [
            'name' => 'Visitor', 'contact' => '123', 'service' => 'academy',
            'date' => now()->toDateString(), 'company' => 'Retired counterparty',
        ])->assertUnprocessable()->assertJsonValidationErrors('company');
        $this->assertDatabaseCount('visitors', 0);
        $visitor = Visitor::create([
            'name' => 'Visitor', 'contact' => '123', 'service' => 'academy',
            'marketer' => '',
            'date' => now()->toDateString(),
        ]);
        $this->putJson('/api/visitors/'.$visitor->id, ['name' => 'Changed', 'company' => 'Retired'])
            ->assertUnprocessable()->assertJsonValidationErrors('company');
        $this->assertSame('Visitor', $visitor->fresh()->name);

        $contract = Contract::factory()->create();
        $this->putJson('/api/contracts/'.$contract->id, ['company_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('company_id');
    }

    public function test_company_schema_and_permissions_stay_retired_after_reseeding(): void
    {
        $this->assertFalse(Schema::hasTable('companies'));
        foreach (['contracts', 'incomes', 'expenses'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'company_id'));
        }
        $this->assertFalse(Schema::hasColumn('visitors', 'company'));
        // Only the permanently retired ledgers stay retired. The
        // contract-driven commission ledger was deliberately restored, so its
        // grants are covered by the live-permission assertion below instead.
        $names = [
            'view_companies', 'create_companies', 'edit_companies', 'delete_companies',
            'view_invoices', 'create_invoices', 'edit_invoices', 'void_invoices', 'delete_invoices',
            'view_payment_details', 'create_payment_details', 'edit_payment_details', 'delete_payment_details',
            'view_outsourcing', 'manage_outsourcing_settlements', 'approve_outsourcing', 'pay_outsourcing',
        ];
        $role = Role::where('name', 'staff')->firstOrFail();
        foreach ($names as $name) {
            $permission = Permission::create(['name' => $name, 'category' => 'crm']);
            $role->permissions()->attach($permission->id);
        }
        $ids = Permission::whereIn('name', $names)->pluck('id');
        foreach (['000004_retire_commission_and_outsourcing_permissions', '000006_retire_invoice_permissions', '000008_retire_company_permissions'] as $name) {
            $migration = require database_path('migrations/2026_10_08_'.$name.'.php');
            $migration->up();
            $migration->up();
        }
        $this->assertSame(0, DB::table('role_permissions')->whereIn('permission_id', $ids)->count());
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->assertSame(0, Permission::withTrashed()->whereIn('name', $names)->count());
        // The restored commission workspace keeps its grants across a reseed.
        $this->assertSame(4, Permission::query()->whereIn('name', [
            'view_commissions', 'manage_commission_settlements', 'approve_commissions', 'pay_commissions',
        ])->count());
    }

    public function test_metadata_cleanup_removes_dead_entities_and_links_without_removing_current_audits_or_branding(): void
    {
        foreach (['companies', 'invoices', 'commission_accruals', 'outsourcing_accruals'] as $resource) {
            AuditResourceSetting::create(['resource_key' => $resource, 'enabled' => true]);
            AuditLog::create([
                'event' => 'created', 'table_name' => $resource, 'subject_type' => $resource,
                'auditable_type' => 'RetiredModel', 'auditable_id' => 1,
            ]);
        }
        $audit = AuditLog::create([
            'event' => 'updated', 'table_name' => 'incomes', 'subject_type' => 'incomes',
            'auditable_type' => Income::class, 'auditable_id' => 1,
            'old_values' => ['amount' => 20, 'invoice_amount' => 20],
            'new_values' => ['amount' => 25, 'company_id' => 1, 'nested' => ['invoice_id' => 1, 'our_company' => ['name' => 'Our Firm']]],
            'changed_columns' => ['amount', 'invoice_amount', 'company_id'],
        ]);
        $notifications = [];
        foreach (['invoice.issued', 'commission.paid', 'outsourcing.paid'] as $key) {
            $notification = Notification::create(['notification_key' => $key, 'date_time' => now()]);
            $notification->deliverTo([auth()->id()]);
            $notifications[] = $notification->id;
        }
        $oldLink = Notification::create(['notification_key' => 'system.changed', 'go_to_url' => '/companies/1', 'date_time' => now()]);
        $current = Notification::create(['notification_key' => 'income.recorded', 'go_to_url' => '/income', 'date_time' => now()]);
        // Auditing is opt-in; this cleanup test asserts that the current
        // income resource survives, so it must be enabled explicitly.
        AuditResourceSetting::updateOrCreate(['resource_key' => 'incomes'], ['enabled' => true]);
        $migration = require database_path('migrations/2026_10_08_000009_purge_retired_finance_metadata.php');
        $migration->up();
        $migration->up();

        $this->assertSame(0, AuditResourceSetting::whereIn('resource_key', ['companies', 'invoices', 'commission_accruals', 'outsourcing_accruals'])->count());
        $this->assertSame(0, AuditLog::whereIn('subject_type', ['companies', 'invoices', 'commission_accruals', 'outsourcing_accruals'])->count());
        $this->assertNotNull($audit->fresh());
        $this->assertSame(['amount' => 20], $audit->fresh()->old_values);
        $this->assertSame(['amount' => 25, 'nested' => ['our_company' => ['name' => 'Our Firm']]], $audit->fresh()->new_values);
        $this->assertSame(['amount'], $audit->fresh()->changed_columns);
        $this->assertSame(0, DB::table('notification_user')->whereIn('notification_id', $notifications)->count());
        $this->assertNull($oldLink->fresh());
        $this->assertNotNull($current->fresh());
        $this->assertDatabaseHas('audit_resource_settings', ['resource_key' => 'incomes', 'enabled' => true]);
    }
}
