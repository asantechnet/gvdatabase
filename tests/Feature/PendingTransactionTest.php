<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Currency;
use App\Models\Applicant;
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
        $applicant = $this->postJson('/api/applicants', ['name' => 'Pending Case'])->assertCreated()->json('data');
        $employee = Employee::factory()->create();

        $income = $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Tuition fee', 'amount' => 100, 'currency_id' => $currency->id, 'applicant_id' => $applicant['id'], 'employee_id' => $employee->id])->assertCreated()->json('data');
        $approver = User::factory()->create(); $approver->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/pending-transactions/{$income['id']}/approve")->assertOk();
        $this->postJson("/api/pending-transactions/{$income['id']}/post")->assertOk();
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
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Deposit', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertCreated();
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertUnprocessable();
        // Office-level expense without an applicant is unaffected.
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Office rent', 'amount' => 50, 'currency_id' => $currency->id])->assertCreated();

        Contract::factory()->create(['applicant_id' => $applicant->id, 'status' => 'active', 'is_cancelled' => false]);
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertCreated();

        // A cancelled contract no longer satisfies the rule.
        Contract::query()->where('applicant_id', $applicant->id)->update(['is_cancelled' => true]);
        $this->postJson('/api/pending-transactions', ['transaction_type' => 'expense', 'reason' => 'Embassy fee', 'amount' => 50, 'currency_id' => $currency->id, 'applicant_id' => $applicant->id])->assertUnprocessable();
    }
}
