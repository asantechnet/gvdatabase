<?php

namespace Tests\Feature;

use App\Models\ApplicantUniversity;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalOperationalReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_contract_finance_and_university_reports_are_case_aware(): void
    {
        $country = Country::create(['name' => 'Canada', 'code' => 'CAN', 'is_active' => true]);
        $university = University::create(['name' => 'Ottawa University', 'country_id' => $country->id, 'is_active' => true]);
        $employee = Employee::factory()->create();
        $case = $this->postJson('/api/applicants', ['name' => 'Finance Case', 'country_id' => $country->id, 'university_id' => $university->id, 'responsible_employee_id' => $employee->id])->assertCreated()->json('data');
        ApplicantUniversity::create(['applicant_id' => $case['id'], 'university_id' => $university->id, 'status' => 'admitted']);

        $template = ContractTemplate::factory()->create(['contract_type_id' => ContractType::query()->firstOrFail()->id]);
        Contract::factory()->create(['template_id' => $template->id, 'contract_type_id' => $template->contract_type_id, 'applicant_id' => $case['id'], 'employee_id' => $employee->id, 'status' => 'active']);
        $currency = Currency::factory()->create(['code' => 'USD']);
        Income::create(['applicant_id' => $case['id'], 'employee_id' => $employee->id, 'currency_id' => $currency->id, 'base_currency_id' => $currency->id, 'type' => 'Case payment', 'description' => 'Admission deposit', 'amount' => 500, 'quantity' => 1, 'rate' => 1, 'cnv_curr_shrt' => 'USD', 'base_curr_shrt' => 'USD', 'date' => now()->toDateString()]);

        $this->getJson('/api/educational-reports/contracts')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.rows.0.applicant_id', $case['id']);
        $this->getJson('/api/educational-reports/financial')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.rows.0.record_type', 'income');
        $this->getJson('/api/educational-reports/universities')->assertOk()->assertJsonPath('data.rows.0.university', 'Ottawa University')->assertJsonPath('data.rows.0.admitted', 1);
    }

    public function test_service_profile_must_match_its_linked_case_when_both_are_known(): void
    {
        $canada = Country::create(['name' => 'Canada', 'code' => 'CAN', 'is_active' => true]);
        $australia = Country::create(['name' => 'Australia', 'code' => 'AUS', 'is_active' => true]);
        $case = $this->postJson('/api/applicants', ['name' => 'Profile Case', 'country_id' => $canada->id])->assertCreated()->json('data');

        $this->postJson('/api/educational-services/scholarships', ['applicant_id' => $case['id'], 'country_id' => $australia->id, 'employee_id' => Employee::factory()->create()->id, 'currency_id' => Currency::factory()->create()->id, 'date' => now()->toDateString(), 'fee' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors('country_id');
    }
}
