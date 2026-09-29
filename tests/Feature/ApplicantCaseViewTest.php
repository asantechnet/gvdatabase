<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Applicant;
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
        $a = $this->postJson('/api/applicants', ['name' => 'Case One', 'notes' => 'Initial note'])->assertCreated()->json('data');

        $pending = $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Tuition fee', 'amount' => 200, 'currency_id' => $currency->id, 'applicant_id' => $a['id']])->assertCreated()->json('data');
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
        $employee = \App\Models\Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Case Two'])->assertCreated()->json('data');
        $this->seedApplicantDocuments($a['id']);
        $this->postJson('/api/educational-services/scholarships', ['applicant_id' => $a['id'], 'employee_id' => $employee->id, 'currency_id' => $currency->id, 'date' => now()->toDateString(), 'fee' => 750, 'status' => 'active'])->assertCreated();
        $this->postJson('/api/educational-services/test-offers', ['applicant_id' => $a['id'], 'employee_id' => $employee->id, 'currency_id' => $currency->id, 'date' => now()->toDateString(), 'fee' => 120, 'status' => 'active'])->assertCreated();

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertCount(2, $case['services']);
        $this->assertSame($currency->code, $case['services'][0]['currency']['code']);
    }

    public function test_case_fields_are_editable_with_validation(): void
    {
        $country = \App\Models\Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $employee = \App\Models\Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Case Three'])->assertCreated()->json('data');

        $updated = $this->putJson("/api/applicants/{$a['id']}", ['country_id' => $country->id, 'responsible_employee_id' => $employee->id])->assertOk()->json('data');
        $this->assertSame($country->id, $updated['country_id']);
        $this->assertSame($employee->id, $updated['responsible_employee_id']);

        $this->putJson("/api/applicants/{$a['id']}", ['university_id' => 999999])->assertUnprocessable();
    }

    public function test_case_family_contact_and_grade_metric_are_captured_edited_and_validated(): void
    {
        $a = $this->postJson('/api/applicants', [
            'name' => 'Family Case',
            'father_name' => 'Abdul Rahman',
            'father_phone' => '+93700123456',
            'percentage' => 78.5,
        ])->assertCreated()->json('data');

        $this->assertSame('Abdul Rahman', $a['father_name']);
        $this->assertSame('+93700123456', $a['father_phone']);
        // The applicant endpoints serialize the model, so the decimal cast
        // arrives as a numeric string; the stored value is what matters.
        $this->assertSame(78.5, (float) $a['percentage']);

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertSame(78.5, (float) $case['percentage']);

        // Editing one field keeps the others, and the metric accepts decimals.
        $updated = $this->putJson("/api/applicants/{$a['id']}", ['percentage' => 91.25, 'father_phone' => '+93700999999'])->assertOk()->json('data');
        $this->assertSame(91.25, (float) $updated['percentage']);
        $this->assertSame('+93700999999', $updated['father_phone']);
        $this->assertSame('Abdul Rahman', $updated['father_name']);

        // The grade metric is a 0-100 percentage and the guardian fields are bounded text.
        $this->putJson("/api/applicants/{$a['id']}", ['percentage' => 120])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['percentage' => -0.5])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['percentage' => 'not-a-number'])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['father_name' => str_repeat('a', 256)])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['father_phone' => str_repeat('9', 51)])->assertUnprocessable();

        // An intake record can still be cleared before the transcript arrives.
        $cleared = $this->putJson("/api/applicants/{$a['id']}", ['percentage' => null, 'father_name' => null])->assertOk()->json('data');
        $this->assertNull($cleared['percentage']);
        $this->assertNull($cleared['father_name']);
        $this->assertSame('+93700999999', $cleared['father_phone']);
    }
}
