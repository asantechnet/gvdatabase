<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftFinanceRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FinanceTypeSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());

        return $user;
    }

    private function currency(): Currency
    {
        return Currency::factory()->create([
            'code' => 'USD',
            'symbol' => '$',
            'is_base' => true,
            'is_active' => true,
            'rate' => 1,
            'decimal_places' => 2,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function incomePayload(Currency $currency, array $overrides = []): array
    {
        return array_merge([
            'type' => 'Service',
            'description' => 'Applicant deposit',
            'amount' => 20,
            'quantity' => 1,
            'currency_id' => $currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
            'status' => 'draft',
        ], $overrides);
    }

    public function test_staff_can_create_a_draft_and_sees_only_their_own_records(): void
    {
        $currency = $this->currency();
        $staff = $this->userWithRole('staff');
        $other = $this->userWithRole('staff');

        Sanctum::actingAs($staff, ['access']);
        $draft = $this->postJson('/api/incomes', $this->incomePayload($currency))
            ->assertCreated()
            ->json('data');

        $this->assertSame('draft', $draft['status']);
        $this->assertNull($draft['receipt_number']);

        // A draft is the only thing this grant can write.
        $this->postJson('/api/incomes', $this->incomePayload($currency, ['status' => 'approved']))
            ->assertForbidden();

        Sanctum::actingAs($other, ['access']);
        $this->postJson('/api/incomes', $this->incomePayload($currency, ['description' => 'Other draft']))
            ->assertCreated();

        $list = $this->getJson('/api/incomes?per_page=25')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame($other->id, $list[0]['recorded_by']);

        // The own scope applies to a single row and its printable receipt too.
        $this->getJson("/api/incomes/{$draft['id']}")->assertForbidden();
        $this->getJson("/api/incomes/{$draft['id']}/receipt")->assertForbidden();
    }

    public function test_a_draft_is_not_ledger_money_or_numbered_until_approved(): void
    {
        $currency = $this->currency();
        // A manager holds both create and approve, so this also proves that
        // self-approval is allowed.
        $manager = $this->userWithRole('manager');
        $applicant = Applicant::factory()->create(['status' => 'active']);
        $contract = Contract::factory()->create([
            'applicant_id' => $applicant->id,
            'status' => 'active',
            'is_cancelled' => false,
        ]);
        $line = ApplicantContract::query()->create([
            'applicant_id' => $applicant->id,
            'contract_id' => $contract->id,
            'service' => 'scholarship',
            'fee' => 150,
            'currency_id' => $currency->id,
            'currency_rate' => 1,
        ]);

        Sanctum::actingAs($manager, ['access']);

        // Direct recording still posts immediately; only an explicit draft
        // waits for approval.
        $direct = $this->postJson('/api/incomes', array_diff_key($this->incomePayload($currency), ['status' => true]))
            ->assertCreated()
            ->json('data');
        $this->assertSame('approved', $direct['status']);
        $this->assertStringStartsWith('RCPT-', $direct['receipt_number']);

        $draft = $this->postJson('/api/incomes', $this->incomePayload($currency, [
            'applicant_contract_id' => $line->id,
            'amount' => 100,
        ]))->assertCreated()->json('data');

        $this->assertNull($draft['receipt_number']);
        $this->assertEqualsWithDelta(0.0, (float) $line->fresh()->amount_paid, 0.001);

        // A draft is not a receipt yet.
        $this->getJson("/api/incomes/{$draft['id']}/receipt")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.finance_record_not_approved');

        $approved = $this->patchJson("/api/incomes/{$draft['id']}/approve")
            ->assertOk()
            ->json('data');

        $this->assertSame('approved', $approved['status']);
        $this->assertStringStartsWith('RCPT-', $approved['receipt_number']);
        $this->assertEqualsWithDelta(100.0, (float) $line->fresh()->amount_paid, 0.001);
    }

    public function test_approval_needs_the_approval_permission_and_rejection_needs_a_reason(): void
    {
        $currency = $this->currency();
        $staff = $this->userWithRole('staff');
        $manager = $this->userWithRole('manager');

        Sanctum::actingAs($staff, ['access']);
        $draft = $this->postJson('/api/incomes', $this->incomePayload($currency))
            ->assertCreated()
            ->json('data');

        $this->patchJson("/api/incomes/{$draft['id']}/approve")->assertForbidden();

        Sanctum::actingAs($manager, ['access']);
        $this->patchJson("/api/incomes/{$draft['id']}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');

        $rejected = $this->patchJson("/api/incomes/{$draft['id']}/reject", [
            'rejection_reason' => 'Missing bank receipt',
        ])->assertOk()->json('data');

        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('Missing bank receipt', $rejected['rejection_reason']);

        // A rejected row is terminal for the approval workflow.
        $this->patchJson("/api/incomes/{$draft['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.finance_record_not_draft');
    }

    public function test_expense_drafts_follow_the_same_workflow(): void
    {
        $currency = $this->currency();
        $staff = $this->userWithRole('staff');
        $manager = $this->userWithRole('manager');

        Sanctum::actingAs($staff, ['access']);
        $draft = $this->postJson('/api/expenses', [
            'type' => 'Utilities',
            'description' => 'Office power',
            'amount' => 50,
            'quantity' => 1,
            'currency_id' => $currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
            'status' => 'draft',
        ])->assertCreated()->json('data');

        $this->assertSame('draft', $draft['status']);
        $this->assertCount(1, $this->getJson('/api/expenses?per_page=25')->assertOk()->json('data'));

        $this->patchJson("/api/expenses/{$draft['id']}/approve")->assertForbidden();

        Sanctum::actingAs($manager, ['access']);
        $this->patchJson("/api/expenses/{$draft['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_payment_request_endpoints_and_schema_are_gone(): void
    {
        Sanctum::actingAs($this->userWithRole('super_admin'), ['access']);

        $this->getJson('/api/payment-requests')->assertNotFound();
        $this->postJson('/api/payment-requests', [])->assertNotFound();
        $this->getJson('/api/pending-transactions')->assertNotFound();

        $this->assertFalse(Schema::hasTable('pending_transactions'));
        $this->assertFalse(Schema::hasColumn('incomes', 'pending_transaction_id'));
        $this->assertFalse(Schema::hasColumn('expenses', 'pending_transaction_id'));
    }
}
