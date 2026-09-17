<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantCaseViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_case_view_connects_finance_services_and_notes(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id, 'notes' => 'Initial note'])->assertCreated()->json('data');

        $pending = $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Tuition fee', 'amount' => 200, 'currency_id' => $currency->id, 'customer_id' => $customer->id, 'applicant_id' => $a['id']])->assertCreated()->json('data');
        $approver = User::factory()->create(); $approver->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/pending-transactions/{$pending['id']}/approve")->assertOk();
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertOk();

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertCount(1, $case['pending_transactions']);
        $this->assertSame('posted', $case['pending_transactions'][0]['approval_status']);
        $this->assertCount(1, $case['incomes']);
        $this->assertSame(200.0, (float) $case['incomes'][0]['amount']);
        $this->assertSame([], $case['expenses']);
        $this->assertSame('Initial note', $case['notes']);

        // Case notes are editable through the standard update path.
        $updated = $this->putJson("/api/applicants/{$a['id']}", ['notes' => 'Called the family; documents promised Friday'])->assertOk()->json('data');
        $this->assertSame('Called the family; documents promised Friday', $updated['notes']);
    }

    public function test_case_view_lists_sold_services(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();
        $employee = \App\Models\Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');
        $this->postJson('/api/educational-services/scholarships', ['customer_id' => $customer->id, 'applicant_id' => $a['id'], 'employee_id' => $employee->id, 'currency_id' => $currency->id, 'date' => now()->toDateString(), 'fee' => 750, 'status' => 'active'])->assertCreated();
        $this->postJson('/api/educational-services/test-offers', ['customer_id' => $customer->id, 'applicant_id' => $a['id'], 'employee_id' => $employee->id, 'currency_id' => $currency->id, 'date' => now()->toDateString(), 'fee' => 120, 'status' => 'active'])->assertCreated();

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertCount(1, $case['scholarships']);
        $this->assertSame($currency->code, $case['scholarships'][0]['currency']['code']);
        $this->assertCount(1, $case['test_offers']);
    }
}
