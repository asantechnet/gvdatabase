<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_approved_income_posts_once(): void
    {
        $creator = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $pending = $this->postJson('/api/payment-requests', ['transaction_type' => 'income', 'reason' => 'Deposit', 'amount' => 20, 'currency_id' => $currency->id])->assertCreated()->json('data');
        $this->postJson("/api/payment-requests/{$pending['id']}/post")->assertUnprocessable();
        $this->postJson("/api/payment-requests/{$pending['id']}/approve")->assertUnprocessable();
        $approver = User::factory()->create();
        $approver->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/payment-requests/{$pending['id']}/approve")->assertOk();
        $this->postJson("/api/payment-requests/{$pending['id']}/post")->assertOk();
        $this->postJson("/api/payment-requests/{$pending['id']}/post")->assertOk();
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', ['pending_transaction_id' => $pending['id']]);
    }

    public function test_posted_records_keep_case_attribution(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $applicant = $this->postJson('/api/applicants', ['name' => 'Pending Case'])->assertCreated()->json('data');
        $employee = Employee::factory()->create();

        $income = $this->postJson('/api/payment-requests', ['transaction_type' => 'income', 'reason' => 'Tuition fee', 'amount' => 100, 'currency_id' => $currency->id, 'applicant_id' => $applicant['id'], 'employee_id' => $employee->id])->assertCreated()->json('data');
        $approver = User::factory()->create();
        $approver->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/payment-requests/{$income['id']}/approve")->assertOk();
        $this->postJson("/api/payment-requests/{$income['id']}/post")->assertOk();
        $this->assertDatabaseHas('incomes', ['pending_transaction_id' => $income['id'], 'applicant_id' => $applicant['id'], 'employee_id' => $employee->id]);

        // The posted income is filed against the same single applicant.
        $this->assertSame($applicant['id'], $income['applicant_id']);
    }

    public function test_applicant_expenses_require_an_active_contract(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $applicant = Applicant::factory()->create();

        // No active contract: applicant income still flows, applicant expense is blocked.
        $this->postJson('/api/payment-requests', ['transaction_type' => 'income', 'reason' => 'Deposit', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertCreated();
        $this->postJson('/api/payment-requests', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertUnprocessable();
        // Office-level expense without an applicant is unaffected.
        $this->postJson('/api/payment-requests', ['transaction_type' => 'expense', 'reason' => 'Office rent', 'amount' => 50, 'currency_id' => $currency->id])->assertCreated();

        Contract::factory()->create(['applicant_id' => $applicant->id, 'status' => 'active', 'is_cancelled' => false]);
        $this->postJson('/api/payment-requests', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertCreated();

        // A cancelled contract no longer satisfies the rule.
        Contract::query()->where('applicant_id', $applicant->id)->update(['is_cancelled' => true]);
        $this->postJson('/api/payment-requests', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertUnprocessable();
    }

    public function test_salary_is_no_longer_a_payment_request_type(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);

        // Payroll owns salary records, so a payment request may only be money in
        // (income) or a cost going out (expense).
        $this->postJson('/api/payment-requests', [
            'transaction_type' => 'salary',
            'reason' => 'Payroll',
            'amount' => 500,
            'currency_id' => $currency->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction_type');
    }

    public function test_payment_request_receipt_returns_printable_payload(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'is_base' => true, 'rate' => 1]);
        $pending = $this->postJson('/api/payment-requests', [
            'transaction_type' => 'income',
            'reason' => 'Tuition deposit',
            'amount' => 250,
            'currency_id' => $currency->id,
        ])->assertCreated()->json('data');

        $payload = $this->getJson("/api/payment-requests/{$pending['id']}/receipt")->assertOk()->json('data');

        // The payment request prints through the receipt template: a stable
        // serial, the request data in the income slot and the request block.
        $this->assertSame('loan', $payload['document']);
        $this->assertStringStartsWith('PR-', $payload['serial_number']);
        $this->assertSame($pending['id'], $payload['income']['id']);
        $this->assertSame('Tuition deposit', $payload['income']['name']);
        $this->assertEqualsWithDelta(250.0, $payload['income']['line_total'], 0.001);
        $this->assertEqualsWithDelta(250.0, $payload['totals']['total_fee'], 0.001);
        // A submitted request is not an approved amount yet.
        $this->assertEqualsWithDelta(0.0, $payload['totals']['paid_amount'], 0.001);
        $this->assertSame('submitted', $payload['loan']['status']);
        // Payment requests are not tied to a contract line.
        $this->assertNull($payload['contract']);
        $this->assertArrayHasKey('safe', $payload['letterhead']);
    }

    public function test_payment_request_receipt_requires_view_permission(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $pending = $this->postJson('/api/payment-requests', [
            'transaction_type' => 'income',
            'reason' => 'Deposit',
            'amount' => 10,
            'currency_id' => $currency->id,
        ])->assertCreated()->json('data');

        $restricted = User::factory()->create();
        Sanctum::actingAs($restricted, ['access']);
        $this->getJson("/api/payment-requests/{$pending['id']}/receipt")->assertForbidden();
    }

    public function test_legacy_loans_and_pending_transactions_paths_are_gone(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/payment-requests')->assertOk();
        $this->getJson('/api/pending-transactions')->assertNotFound();
        $this->getJson('/api/loans')->assertNotFound();
    }
}
