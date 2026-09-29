<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\PackageType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalServiceAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_scholarship_sale_creates_and_keeps_a_traceable_bonus_source(): void
    {
        $applicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($applicant);
        $employee = Employee::factory()->create();
        $replacementEmployee = Employee::factory()->create();
        $currency = Currency::factory()->create();

        $sale = $this->postJson('/api/educational-services/scholarships', [
            'applicant_id' => $applicant->id,
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'date' => '2026-09-17',
            'fee' => 500,
            'bonus_amount' => 75,
            'bonus_rule_source' => 'September referral policy',
        ])->assertCreated()
            ->assertJsonPath('data.date', '2026-09-17')
            ->assertJsonPath('data.applicant.name', $applicant->name)
            ->assertJsonPath('data.employee.name', $employee->name)
            ->assertJsonPath('data.bonus_source.bonus_amount', '75.000000')
            ->json('data');

        $this->putJson("/api/educational-services/scholarships/{$sale['id']}", [
            'employee_id' => $replacementEmployee->id,
            'fee' => 600,
        ])->assertOk();

        $this->assertDatabaseHas('educational_bonus_sources', [
            'source_id' => $sale['id'],
            'employee_id' => $replacementEmployee->id,
            'applicant_id' => $applicant->id,
            'source_amount' => 600,
            'bonus_amount' => 75,
            'rule_source' => 'September referral policy',
        ]);
    }

    public function test_service_options_load_active_package_types_without_a_sort_order_column(): void
    {
        PackageType::create(['name' => 'Zeta package', 'is_active' => true]);
        PackageType::create(['name' => 'Alpha package', 'is_active' => true]);
        PackageType::create(['name' => 'Inactive package', 'is_active' => false]);

        $this->getJson('/api/educational-services/options')
            ->assertOk()
            ->assertJsonPath('data.package_types.0.name', 'Alpha package')
            ->assertJsonPath('data.package_types.1.name', 'Zeta package')
            ->assertJsonMissing(['name' => 'Inactive package']);
    }

    public function test_service_is_attributed_to_the_single_applicant_it_is_issued_to(): void
    {
        $applicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($applicant);
        $employee = Employee::factory()->create();
        $currency = Currency::factory()->create();

        $offer = $this->postJson('/api/educational-services/test-offers', [
            'applicant_id' => $applicant->id,
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'date' => '2026-09-17',
            'fee' => 500,
        ])->assertCreated()->json('data');

        // The sold contract and its service line both hang off that one applicant.
        $this->assertDatabaseHas('contracts', ['applicant_id' => $applicant->id]);
        $this->assertDatabaseHas('contract_service', ['applicant_id' => $applicant->id]);
        $this->assertSame($applicant->id, $offer['applicant_id']);
    }

    public function test_contract_bonus_source_has_an_approval_to_payment_lifecycle(): void
    {
        $applicant = Applicant::factory()->create();
        $employee = Employee::factory()->create();
        $contract = Contract::factory()->create(['applicant_id' => $applicant->id, 'employee_id' => $employee->id]);

        $bonus = $this->postJson('/api/educational-bonus-sources', [
            'source_type' => 'contract',
            'source_id' => $contract->id,
            'source_amount' => 1000,
            'bonus_amount' => 125,
            'rule_source' => 'Signed-contract policy',
        ])->assertCreated()
            ->assertJsonPath('data.applicant.name', $applicant->name)
            ->assertJsonPath('data.employee.name', $employee->name)
            ->json('data');

        $this->putJson("/api/educational-bonus-sources/{$bonus['id']}", ['status' => 'paid'])->assertUnprocessable();
        $this->putJson("/api/educational-bonus-sources/{$bonus['id']}", ['status' => 'approved'])->assertOk();
        $this->putJson("/api/educational-bonus-sources/{$bonus['id']}", ['status' => 'paid'])->assertOk();

        $this->assertDatabaseHas('educational_bonus_sources', ['id' => $bonus['id'], 'status' => 'paid', 'source_amount' => 1000]);
    }
}
