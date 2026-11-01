<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Income;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantContractPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->create([
            'code' => 'USD', 'symbol' => '$', 'is_base' => true, 'rate' => 1,
        ]);

        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($this->user, ['access']);
    }

    private function makeLine(float $fee, ?float $discount = null, string $service = 'scholarship'): ApplicantContract
    {
        $applicant = Applicant::factory()->create(['status' => 'active']);
        $contract = Contract::factory()->create([
            'applicant_id' => $applicant->id,
            'status' => 'active',
            'is_cancelled' => false,
        ]);

        return ApplicantContract::query()->create([
            'contract_id' => $contract->id,
            'service' => $service,
            'fee' => $fee,
            'discount' => $discount ?? 0,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1,
            'applicant_id' => $applicant->id,
        ]);
    }

    private function pay(ApplicantContract $line, float $amount): TestResponse
    {
        return $this->postJson('/api/incomes', [
            'applicant_contract_id' => $line->id,
            'type' => 'Service',
            'description' => 'Instalment',
            'amount' => $amount,
            'quantity' => 1,
            'currency_id' => $this->currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
        ], ['Accept' => 'application/json']);
    }

    public function test_payment_overrides_require_a_separate_finance_permission(): void
    {
        $line = $this->makeLine(100);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('name', 'manager')->firstOrFail());
        Sanctum::actingAs($manager, ['access']);
        $this->putJson("/api/incomes/applicant-contracts/{$line->id}/payment-override", [
            'amount_paid' => 80, 'payment_status' => null,
        ])->assertForbidden();
        $this->assertFalse($line->fresh()->amount_paid_overridden);

        $finance = User::factory()->create();
        $finance->roles()->attach(Role::where('name', 'finance_officer')->firstOrFail());
        Sanctum::actingAs($finance, ['access']);
        $this->putJson("/api/incomes/applicant-contracts/{$line->id}/payment-override", [
            'amount_paid' => 80, 'payment_status' => null,
        ])->assertOk()->assertJsonPath('data.amount_paid_overridden', true)
            ->assertJsonPath('data.payment_status', 'partially_paid');
        $this->assertTrue($finance->hasPermission('override_contract_payments'));
    }

    public function test_amount_overrides_can_be_changed_and_cleared_without_touching_receipts(): void
    {
        $line = $this->makeLine(100);
        $this->pay($line, 20)->assertCreated();
        $income = Income::where('applicant_contract_id', $line->id)->firstOrFail();
        $url = "/api/incomes/applicant-contracts/{$line->id}/payment-override";
        $this->putJson($url, ['amount_paid' => 70, 'payment_status' => null])->assertOk()
            ->assertJsonPath('data.recorded_amount_paid', 20)->assertJsonPath('data.remaining_total', 30);
        $this->putJson($url, ['amount_paid' => 100, 'payment_status' => null])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.remaining_total', 0);
        $this->pay($line, 1)->assertUnprocessable();
        $this->putJson($url, ['amount_paid' => null, 'payment_status' => null])->assertOk()
            ->assertJsonPath('data.amount_paid', 20)->assertJsonPath('data.amount_paid_overridden', false)
            ->assertJsonPath('data.payment_status', 'partially_paid')->assertJsonPath('data.remaining_total', 80);
        $this->assertEquals(20, $income->fresh()->amount);
        $audit = AuditLog::where('subject_type', 'applicant_contracts')->where('auditable_id', $line->id)
            ->where('event', 'updated')->get();
        $this->assertTrue($audit->contains(fn (AuditLog $entry) => in_array('amount_paid_overridden', $entry->changed_columns, true)));
    }

    public function test_status_overrides_are_independent_of_amount_and_collection_limits(): void
    {
        $line = $this->makeLine(100);
        $url = "/api/incomes/applicant-contracts/{$line->id}/payment-override";
        $this->putJson($url, ['amount_paid' => null, 'payment_status' => 'paid'])->assertOk();
        $this->pay($line, 20)->assertCreated();
        $this->assertSame('paid', $line->fresh()->payment_status);
        $this->assertEquals(20, $line->fresh()->amount_paid);
        $this->putJson($url, ['amount_paid' => 50, 'payment_status' => 'unpaid'])->assertOk();
        $this->pay($line, 10)->assertCreated();
        $this->assertEquals(50, $line->fresh()->amount_paid);
        $this->assertSame('unpaid', $line->fresh()->payment_status);
        $this->putJson($url, ['amount_paid' => null, 'payment_status' => 'unpaid'])->assertOk()
            ->assertJsonPath('data.amount_paid', 30)->assertJsonPath('data.payment_status_overridden', true);
        $this->putJson($url, ['amount_paid' => null, 'payment_status' => null])->assertOk()
            ->assertJsonPath('data.payment_status', 'partially_paid');
    }

    public function test_override_payment_limits_credit_the_edited_receipt_and_reject_new_overpayments(): void
    {
        $line = $this->makeLine(100);
        $this->pay($line, 40)->assertCreated();
        $income = Income::where('applicant_contract_id', $line->id)->firstOrFail();
        $url = "/api/incomes/applicant-contracts/{$line->id}/payment-override";
        $this->putJson($url, ['amount_paid' => 80, 'payment_status' => null])->assertOk();
        $this->pay($line, 21)->assertUnprocessable();
        $this->putJson("/api/incomes/{$income->id}", ['amount' => 60, 'revaluation_reason' => 'Payment corrected'])->assertOk();
        $this->putJson("/api/incomes/{$income->id}", ['amount' => 81])->assertUnprocessable();
        $this->putJson($url, ['amount_paid' => 10, 'payment_status' => null])->assertOk();
        $this->putJson("/api/incomes/{$income->id}", ['amount' => 101])->assertUnprocessable();
        $this->putJson("/api/incomes/{$income->id}", ['amount' => 100, 'revaluation_reason' => 'Restored the agreed amount'])->assertOk();
        $this->getJson('/api/incomes/applicant-contracts?applicant_id='.$line->applicant_id)->assertOk()
            ->assertJsonPath('data.0.amount_paid', 10)->assertJsonPath('data.0.recorded_amount_paid', 100);
    }

    public function test_override_commands_validate_values_and_reject_archived_lines(): void
    {
        $line = $this->makeLine(100);
        $url = "/api/incomes/applicant-contracts/{$line->id}/payment-override";
        $this->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['amount_paid', 'payment_status']);
        foreach ([-1, 10000000000000, 'not money', 0.0000001] as $amount) {
            $this->putJson($url, ['amount_paid' => $amount, 'payment_status' => null])->assertUnprocessable()->assertJsonValidationErrors('amount_paid');
        }
        $this->putJson($url, ['amount_paid' => 10, 'payment_status' => 'pending'])->assertUnprocessable();
        $this->assertFalse($line->fresh()->amount_paid_overridden);
        $this->putJson($url, ['amount_paid' => 0, 'payment_status' => null])->assertOk()
            ->assertJsonPath('data.amount_paid_overridden', true)->assertJsonPath('data.payment_status', 'unpaid');
        $line->delete();
        $this->putJson($url, ['amount_paid' => null, 'payment_status' => null])->assertNotFound();
    }

    public function test_multi_service_payments_keep_distinct_receipts_and_preserve_completed_requests_on_failure(): void
    {
        $firstLine = $this->makeLine(100);
        $secondLine = $this->makeLine(800, service: 'test_offer');
        $afn = Currency::factory()->create(['code' => 'AFN', 'rate' => 70, 'is_base' => false]);
        $secondLine->contract->update(['applicant_id' => $firstLine->applicant_id]);
        $secondLine->update(['applicant_id' => $firstLine->applicant_id, 'currency_id' => $afn->id, 'currency_rate' => 80]);
        $first = $this->pay($firstLine, 100)->assertCreated()->json('data');
        $secondPayment = [
            'applicant_id' => $firstLine->applicant_id, 'applicant_contract_id' => $secondLine->id,
            'currency_id' => $afn->id, 'amount' => 801, 'rate' => 80, 'quantity' => 1,
            'type' => 'Service', 'date' => now()->toDateString(),
        ];
        $this->postJson('/api/incomes', $secondPayment)->assertUnprocessable();
        $this->assertEquals(1, Income::count());
        $this->assertEquals(70, $afn->fresh()->rate);
        $this->assertSame('paid', $firstLine->fresh()->payment_status);
        $second = $this->postJson('/api/incomes', [...$secondPayment, 'amount' => 400])->assertCreated()->json('data');
        $this->assertNotSame($first['receipt_number'], $second['receipt_number']);
        $this->assertSame($firstLine->id, $first['applicant_contract_id']);
        $this->assertSame($secondLine->id, $second['applicant_contract_id']);
        $this->assertEquals(2, Income::count());
        $this->assertEquals(400, $secondLine->fresh()->amount_paid);
        $this->assertEquals(80, $afn->fresh()->rate);
        foreach ([$first, $second] as $receipt) {
            $this->assertCount(1, $this->getJson('/api/incomes/'.$receipt['id'].'/receipt')->assertOk()->json('data.services'));
        }
    }

    public function test_override_permission_migration_is_additive_and_idempotent(): void
    {
        $permission = Permission::where('name', 'override_contract_payments')->firstOrFail();
        $admin = Role::where('name', 'admin')->firstOrFail();
        $finance = Role::where('name', 'finance_officer')->firstOrFail();
        $staff = Role::where('name', 'staff')->firstOrFail();
        DB::table('role_permissions')->where('permission_id', $permission->id)->delete();
        DB::table('role_permissions')->insert(['role_id' => $staff->id, 'permission_id' => $permission->id]);
        $migration = require database_path('migrations/2026_10_07_000013_seed_contract_payment_override_permission.php');
        $migration->up();
        $migration->up();
        foreach ([$admin, $finance, $staff] as $role) {
            $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        }
        $this->assertEquals(3, DB::table('role_permissions')->where('permission_id', $permission->id)->count());
        $migration->down();
        $this->assertEquals(3, DB::table('role_permissions')->where('permission_id', $permission->id)->count());
        $this->assertSame($permission->id, Permission::where('name', 'override_contract_payments')->firstOrFail()->id);
    }

    public function test_payments_move_the_line_through_the_payment_states(): void
    {
        $line = $this->makeLine(1000);

        $this->assertSame('unpaid', $line->fresh()->payment_status);

        $this->pay($line, 400)->assertCreated();
        $line->refresh();
        $this->assertSame('partially_paid', $line->payment_status);
        $this->assertEqualsWithDelta(400, (float) $line->amount_paid, 0.001);

        $this->pay($line, 600)->assertCreated();
        $line->refresh();
        $this->assertSame('paid', $line->payment_status);
        $this->assertEqualsWithDelta(1000, (float) $line->amount_paid, 0.001);
    }

    public function test_discount_reduces_the_amount_owed(): void
    {
        $line = $this->makeLine(1000, 250);

        $this->pay($line, 750)->assertCreated();

        $line->refresh();
        $this->assertSame('paid', $line->payment_status);
        $this->assertEqualsWithDelta(750, (float) $line->amount_paid, 0.001);
    }

    public function test_a_payment_may_not_exceed_the_remaining_balance(): void
    {
        $line = $this->makeLine(500);

        $this->pay($line, 400)->assertCreated();
        $this->pay($line, 200)->assertStatus(422);

        $line->refresh();
        $this->assertEqualsWithDelta(400, (float) $line->amount_paid, 0.001);
        $this->assertSame('partially_paid', $line->payment_status);
    }

    public function test_deleting_a_receipt_returns_the_line_to_its_previous_state(): void
    {
        $line = $this->makeLine(300);
        $this->pay($line, 300)->assertCreated();
        $this->assertSame('paid', $line->fresh()->payment_status);

        $income = Income::query()->where('applicant_contract_id', $line->id)->firstOrFail();
        $this->deleteJson("/api/incomes/{$income->id}")->assertNoContent();

        $line->refresh();
        $this->assertSame('unpaid', $line->payment_status);
        $this->assertEqualsWithDelta(0, (float) $line->amount_paid, 0.001);
    }

    public function test_receipt_describes_the_single_service_paid(): void
    {
        $line = $this->makeLine(900, 100);
        $this->pay($line, 400)->assertCreated();

        $income = Income::query()->where('applicant_contract_id', $line->id)->firstOrFail();
        $payload = $this->getJson("/api/incomes/{$income->id}/receipt")->assertOk()->json('data');

        $this->assertCount(1, $payload['services']);
        $this->assertSame('scholarship', $payload['services'][0]['service']);
        $this->assertEqualsWithDelta(800, $payload['totals']['total_fee'], 0.001);
        $this->assertEqualsWithDelta(400, $payload['totals']['paid_amount'], 0.001);
        $this->assertEqualsWithDelta(400, $payload['totals']['balance'], 0.001);
        $this->assertArrayNotHasKey('invoice', $payload);
    }

    public function test_an_income_without_a_line_is_still_allowed(): void
    {
        $applicant = Applicant::factory()->create();

        $this->postJson('/api/incomes', [
            'applicant_id' => $applicant->id,
            'type' => 'Other',
            'amount' => 50,
            'quantity' => 1,
            'currency_id' => $this->currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('incomes', [
            'applicant_id' => $applicant->id,
            'applicant_contract_id' => null,
        ]);
    }

    public function test_applicant_contracts_endpoint_lists_the_line_balances(): void
    {
        $line = $this->makeLine(800);
        $this->pay($line, 300)->assertCreated();

        $rows = $this->getJson('/api/incomes/applicant-contracts?applicant_id='.$line->applicant_id)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($line->id, $rows[0]['id']);
        $this->assertEqualsWithDelta(800, (float) $rows[0]['payable_total'], 0.001);
        $this->assertEqualsWithDelta(300, (float) $rows[0]['amount_paid'], 0.001);
        $this->assertEqualsWithDelta(500, (float) $rows[0]['remaining_total'], 0.001);
        $this->assertSame('partially_paid', $rows[0]['payment_status']);
    }

    public function test_mismatched_payment_currencies_are_rejected_without_repricing(): void
    {
        $line = $this->makeLine(100);
        $other = Currency::factory()->create(['rate' => 70, 'code' => 'AFN', 'is_base' => false]);
        $this->postJson('/api/incomes', [
            'applicant_contract_id' => $line->id, 'type' => 'Service', 'amount' => 7000,
            'quantity' => 1, 'currency_id' => $other->id, 'rate' => 70, 'date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('currency_id');
        $this->assertDatabaseCount('incomes', 0);

        $id = $this->pay($line, 50)->assertCreated()->json('data.id');
        $this->putJson("/api/incomes/{$id}", ['currency_id' => $other->id, 'rate' => 80, 'revaluation_reason' => 'Switch payment currency'])
            ->assertUnprocessable()->assertJsonValidationErrors('currency_id');
        $this->assertEquals(70, $other->fresh()->rate);
        $this->assertEquals(50, $line->fresh()->amount_paid);
    }

    public function test_description_and_null_rate_edits_preserve_the_receipt_snapshot(): void
    {
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'is_base' => false, 'rate' => 1]);
        $line = $this->makeLine(100);
        $id = $this->pay($line, 100)->assertCreated()->json('data.id');
        $this->currency->update(['rate' => 2]);
        $this->putJson("/api/incomes/{$id}", [
            'description' => 'Corrected note', 'currency_id' => $this->currency->id, 'rate' => null,
        ])->assertOk();
        $income = Income::findOrFail($id);
        $this->assertEquals(1, $income->rate);
        $this->assertEquals(100, $income->base_amount);
        $this->assertEquals(2, $this->currency->fresh()->rate);
        $this->assertSame('paid', $line->fresh()->payment_status);
    }

    public function test_supplied_rates_are_remembered_and_failed_payments_roll_them_back(): void
    {
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'is_base' => false, 'rate' => 1]);
        $line = $this->makeLine(100);
        $payload = [
            'applicant_contract_id' => $line->id, 'type' => 'Service', 'amount' => 300,
            'quantity' => 1, 'currency_id' => $this->currency->id, 'rate' => 2, 'date' => now()->toDateString(),
        ];
        $this->postJson('/api/incomes', $payload)->assertUnprocessable();
        $this->assertEquals(1, $this->currency->fresh()->rate);
        $payload['amount'] = 40;
        $id = $this->postJson('/api/incomes', $payload)->assertCreated()->json('data.id');
        $this->assertEquals(2, $this->currency->fresh()->rate);
        $this->assertEquals(20, $line->fresh()->amount_paid);
        $this->putJson("/api/incomes/{$id}", ['rate' => 4, 'revaluation_reason' => 'Corrected the agreed rate'])->assertOk();
        $this->assertEquals(4, $this->currency->fresh()->rate);
        $this->assertEquals(10, $line->fresh()->amount_paid);
        $this->putJson("/api/incomes/{$id}", ['amount' => 1000, 'rate' => 3])->assertUnprocessable();
        $this->assertEquals(4, $this->currency->fresh()->rate);
        $this->assertEquals(10, $line->fresh()->amount_paid);
    }

    public function test_repricing_recalculates_payment_state_and_respects_overrides(): void
    {
        $line = $this->makeLine(100);
        $this->pay($line, 60)->assertCreated();
        $line->refresh()->update(['fee' => 50]);
        $this->assertSame('paid', $line->fresh()->payment_status);
        $line->refresh()->update(['fee' => 100, 'discount' => 40]);
        $this->assertSame('paid', $line->fresh()->payment_status);
        $line->refresh()->update(['discount' => 0, 'currency_rate' => 0.5]);
        $this->assertEquals(30, $line->fresh()->amount_paid);
        $this->assertSame('partially_paid', $line->fresh()->payment_status);
        $line->refresh()->update(['amount_paid_overridden' => true, 'amount_paid' => 90, 'payment_status_overridden' => true, 'payment_status' => 'unpaid']);
        $line->refresh()->update(['discount' => 20]);
        $this->assertEquals(90, $line->fresh()->amount_paid);
        $this->assertSame('unpaid', $line->fresh()->payment_status);
        $line->refresh()->update(['amount_paid_overridden' => false, 'payment_status_overridden' => false]);
        $this->assertEquals(30, $line->fresh()->amount_paid);
        $this->assertSame('partially_paid', $line->fresh()->payment_status);
    }

    public function test_relinking_a_payment_refreshes_both_line_balances(): void
    {
        $first = $this->makeLine(100);
        $second = $this->makeLine(200);
        $id = $this->pay($first, 100)->assertCreated()->json('data.id');
        $this->putJson("/api/incomes/{$id}", ['applicant_contract_id' => $second->id])->assertOk();
        $this->assertEquals(0, $first->fresh()->amount_paid);
        $this->assertSame('unpaid', $first->fresh()->payment_status);
        $this->assertEquals(100, $second->fresh()->amount_paid);
        $this->assertSame('partially_paid', $second->fresh()->payment_status);
    }
}
