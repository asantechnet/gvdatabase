<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ContractRegisterTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    private function contract(array $payload = []): Contract
    {
        $created = $this->createBuiltInContract('scholarship', ['name' => 'Register Case', ...$payload]);

        return Contract::query()->findOrFail($created['id']);
    }

    private function rows(array $query = []): array
    {
        $suffix = $query === [] ? '' : '?'.http_build_query($query);

        return $this->getJson("/api/contracts{$suffix}")->assertOk()->json('data');
    }

    public function test_expiring_filter_lists_only_live_active_contracts_inside_the_window(): void
    {
        $expiring = $this->contract();
        $expiring->update(['status' => 'active', 'end_date' => now()->addDays(10)->toDateString()]);

        $expired = $this->contract();
        $expired->update(['status' => 'active', 'end_date' => now()->subDays(3)->toDateString()]);

        $far = $this->contract();
        $far->update(['status' => 'active', 'end_date' => now()->addDays(90)->toDateString()]);

        $completed = $this->contract();
        $completed->update(['status' => 'completed', 'end_date' => now()->addDays(5)->toDateString()]);

        $cancelled = $this->contract();
        $cancelled->update(['status' => 'active', 'is_cancelled' => true, 'end_date' => now()->addDays(5)->toDateString()]);

        $ids = collect($this->rows(['expiring' => 1]))->pluck('id')->all();

        $this->assertContains($expiring->id, $ids);
        $this->assertContains($expired->id, $ids);
        $this->assertNotContains($far->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_register_reports_role_expiry_and_scheduled_extension_state(): void
    {
        $primary = $this->contract();
        $primary->update(['status' => 'active', 'end_date' => now()->addDays(10)->toDateString()]);

        $extension = $this->contract(['parent_contract_id' => $primary->id]);
        $extension->update([
            'status' => 'active',
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(200)->toDateString(),
        ]);

        $rows = collect($this->rows());
        $primaryRow = $rows->firstWhere('id', $primary->id);
        $extensionRow = $rows->firstWhere('id', $extension->id);

        $this->assertSame('primary', $primaryRow['contract_role']);
        $this->assertSame(10, $primaryRow['days_remaining']);
        $this->assertSame('expiring', $primaryRow['expiry_state']);
        $this->assertSame('active', $primaryRow['display_status']);

        $this->assertSame('extension', $extensionRow['contract_role']);
        $this->assertSame($primary->contract_number, $extensionRow['parent_contract_number']);
        $this->assertTrue($extensionRow['is_scheduled']);
        $this->assertSame('scheduled', $extensionRow['display_status']);
        // A future-dated extension is not expiring yet.
        $this->assertSame('none', $extensionRow['expiry_state']);
    }

    public function test_register_shows_service_line_payment_and_a_defined_not_applicable_state(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1, 'code' => 'AFN', 'symbol' => '؋']);
        $contract = $this->contract(['fee' => 100, 'currency_id' => $currency->id]);
        $contract->update(['status' => 'active']);

        $line = $contract->applicantContracts()->firstOrFail();
        $line->update(['amount_paid' => 40, 'amount_paid_overridden' => true, 'payment_status_overridden' => false]);

        $row = collect($this->rows())->firstWhere('id', $contract->id);

        $this->assertTrue($row['payment']['applicable']);
        $this->assertSame('partially_paid', $row['payment']['status']);
        $this->assertEquals(60.0, (float) $row['payment']['outstanding']);
        $this->assertSame('AFN', $row['payment']['currency']['code']);

        // A contract with no service lines reports not-applicable, not zero.
        $contract->applicantContracts()->delete();
        $row = collect($this->rows())->firstWhere('id', $contract->id);

        $this->assertFalse($row['payment']['applicable']);
        $this->assertNull($row['payment']['status']);
    }

    public function test_payment_state_is_hidden_from_viewers_without_finance_access(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $this->contract(['fee' => 100, 'currency_id' => $currency->id]);

        $role = Role::create(['name' => 'contract_reader_'.uniqid()]);

        foreach (['view_contracts', 'view_all_contracts', 'view_scholarship_contracts'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['category' => 'customer_contracts']));
        }

        $viewer = User::factory()->create();
        $viewer->roles()->attach($role);
        Sanctum::actingAs($viewer, ['access']);

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('payment', $rows[0]);
    }
}
