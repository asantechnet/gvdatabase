<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ApplicantCaseViewTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

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

        // The case view carries the ledger rows themselves; the retired
        // payment-request list is no longer a separate collection.
        Income::create([
            'applicant_id' => $a['id'],
            'type' => 'Service',
            'description' => 'Tuition fee',
            'amount' => 200,
            'quantity' => 1,
            'currency_id' => $currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
            'recorded_by' => User::query()->value('id'),
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $currency->code,
        ]);

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertCount(1, $case['incomes']);
        $this->assertSame('approved', $case['incomes'][0]['status']);
        $this->assertSame(200.0, (float) $case['incomes'][0]['amount']);
        $this->assertSame([], $case['expenses']);
        $this->assertSame('Initial note', $case['notes']);

        // Case notes are editable through the standard update path.
        $updated = $this->putJson("/api/applicants/{$a['id']}", ['notes' => 'Called the family; documents promised Friday'])->assertOk()->json('data');
        $this->assertSame('Called the family; documents promised Friday', $updated['notes']);
    }

    public function test_applicant_list_includes_attached_services_for_finance_viewers(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $contract = $this->createBuiltInContract('scholarship', [
            'name' => 'List service case',
            'fee' => 400,
            'currency_id' => $currency->id,
        ]);

        $rows = $this->getJson('/api/applicants?per_page=100')
            ->assertOk()
            ->json('data.data');
        $row = collect($rows)->firstWhere('id', $contract['applicant_id']);

        $this->assertNotNull($row);
        $this->assertNotEmpty($row['attached_services']);
        $this->assertSame('scholarship', $row['attached_services'][0]['service']);
        $this->assertArrayNotHasKey('fee', $row['attached_services'][0]);
    }

    public function test_case_view_lists_sold_services(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $employee = Employee::factory()->create();
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Case Two', 'fee' => 750, 'currency_id' => $currency->id]);
        $this->createBuiltInContract('scholarship', [
            'parent_contract_id' => $contract['id'], 'fee' => 120, 'currency_id' => $currency->id,
        ]);
        $a = ['id' => $contract['applicant_id']];

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertCount(2, $case['services']);
        $this->assertSame($currency->code, $case['services'][0]['currency']['code']);
    }

    public function test_case_fields_are_editable_with_validation(): void
    {
        $country = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Case Three'])->assertCreated()->json('data');

        $updated = $this->putJson("/api/applicants/{$a['id']}", ['country_id' => $country->id, 'responsible_employee_id' => $employee->id])->assertOk()->json('data');
        $this->assertSame($country->id, $updated['details']['country_id']);
        $this->assertSame($employee->id, $updated['responsible_employee_id']);

        $this->putJson("/api/applicants/{$a['id']}", ['university_id' => 999999])->assertUnprocessable();
    }

    public function test_case_family_contact_and_grade_metric_are_captured_edited_and_validated(): void
    {
        $a = $this->postJson('/api/applicants', [
            'name' => 'Family Case',
            'father_name' => 'Abdul Rahman',
            'father_phone' => '+93700123456',
            'grade_score' => 78.5,
        ])->assertCreated()->json('data');

        $this->assertSame('Abdul Rahman', $a['father_name']);
        $this->assertSame('+93700123456', $a['father_phone']);
        // The applicant endpoints serialize the model, so the decimal cast
        // arrives as a numeric string; the stored value is what matters.
        $this->assertSame(78.5, (float) $a['details']['grade_score']);

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertSame(78.5, (float) $case['details']['grade_score']);

        // Editing one field keeps the others, and the metric accepts decimals.
        $updated = $this->putJson("/api/applicants/{$a['id']}", ['grade_score' => 91.25, 'father_phone' => '+93700999999'])->assertOk()->json('data');
        $this->assertSame(91.25, (float) $updated['details']['grade_score']);
        $this->assertSame('+93700999999', $updated['father_phone']);
        $this->assertSame('Abdul Rahman', $updated['father_name']);

        // Service grade scores allow 0-1000; guardian fields remain bounded text.
        $this->putJson("/api/applicants/{$a['id']}", ['grade_score' => 1001])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['grade_score' => -0.5])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['grade_score' => 'not-a-number'])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['father_name' => str_repeat('a', 256)])->assertUnprocessable();
        $this->putJson("/api/applicants/{$a['id']}", ['father_phone' => str_repeat('9', 51)])->assertUnprocessable();

        // An intake record can still be cleared before the transcript arrives.
        $cleared = $this->putJson("/api/applicants/{$a['id']}", ['grade_score' => null, 'father_name' => null])->assertOk()->json('data');
        $this->assertNull($cleared['details']['grade_score']);
        $this->assertNull($cleared['father_name']);
        $this->assertSame('+93700999999', $cleared['father_phone']);
    }
}
