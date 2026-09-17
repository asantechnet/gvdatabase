<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
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
        $user = User::factory()->create(); $user->roles()->attach($role);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_approved_income_posts_once(): void
    {
        $creator = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $pending = $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Deposit', 'amount' => 20, 'currency_id' => $currency->id])->assertCreated()->json('data');
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertUnprocessable();
        $this->postJson("/api/pending-transactions/{$pending['id']}/approve")->assertUnprocessable();
        $approver = User::factory()->create(); $approver->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/pending-transactions/{$pending['id']}/approve")->assertOk();
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertOk();
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertOk();
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', ['pending_transaction_id' => $pending['id']]);
    }

    public function test_posted_records_keep_case_attribution(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();
        $applicant = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');
        $employee = Employee::factory()->create();

        $income = $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Tuition fee', 'amount' => 100, 'currency_id' => $currency->id, 'customer_id' => $customer->id, 'applicant_id' => $applicant['id'], 'employee_id' => $employee->id])->assertCreated()->json('data');
        $approver = User::factory()->create(); $approver->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/pending-transactions/{$income['id']}/approve")->assertOk();
        $this->postJson("/api/pending-transactions/{$income['id']}/post")->assertOk();
        $this->assertDatabaseHas('incomes', ['pending_transaction_id' => $income['id'], 'customer_id' => $customer->id, 'applicant_id' => $applicant['id'], 'employee_id' => $employee->id]);

        // An applicant paired with another customer's record is rejected.
        $other = Customer::factory()->create();
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Mismatch', 'amount' => 10, 'currency_id' => $currency->id, 'customer_id' => $other->id, 'applicant_id' => $applicant['id']])->assertUnprocessable();
    }

    public function test_customer_expenses_require_an_active_contract(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();

        // No active contract: customer income still flows, customer expense is blocked.
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Deposit', 'amount' => 50, 'currency_id' => $currency->id, 'customer_id' => $customer->id])->assertCreated();
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'customer_id' => $customer->id])->assertUnprocessable();
        // Office-level expense without a customer is unaffected.
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Office rent', 'amount' => 50, 'currency_id' => $currency->id])->assertCreated();

        Contract::factory()->create(['customer_id' => $customer->id, 'status' => 'active', 'is_cancelled' => false]);
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'customer_id' => $customer->id])->assertCreated();

        // A cancelled contract no longer satisfies the rule.
        Contract::query()->where('customer_id', $customer->id)->update(['is_cancelled' => true]);
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'customer_id' => $customer->id])->assertUnprocessable();
    }
}
