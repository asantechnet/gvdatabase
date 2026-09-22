<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
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
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $replacementEmployee = Employee::factory()->create();
        $currency = Currency::factory()->create();

        $sale = $this->postJson('/api/educational-services/scholarships', [
            'customer_id' => $customer->id,
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'date' => '2026-09-17',
            'fee' => 500,
            'bonus_amount' => 75,
            'bonus_rule_source' => 'September referral policy',
        ])->assertCreated()
            ->assertJsonPath('data.date', '2026-09-17')
            ->assertJsonPath('data.customer.name', $customer->name)
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
            'customer_id' => $customer->id,
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

    public function test_service_rejects_an_applicant_owned_by_another_customer(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $applicant = Applicant::query()->create(['customer_id' => $otherCustomer->id]);
        $employee = Employee::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/educational-services/test-offers', [
            'customer_id' => $customer->id,
            'applicant_id' => $applicant->id,
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'date' => '2026-09-17',
            'fee' => 500,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('applicant_id');
    }

    public function test_contract_bonus_source_has_an_approval_to_payment_lifecycle(): void
    {
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $contract = Contract::factory()->create(['customer_id' => $customer->id, 'employee_id' => $employee->id]);

        $bonus = $this->postJson('/api/educational-bonus-sources', [
            'source_type' => 'contract',
            'source_id' => $contract->id,
            'source_amount' => 1000,
            'bonus_amount' => 125,
            'rule_source' => 'Signed-contract policy',
        ])->assertCreated()
            ->assertJsonPath('data.customer.name', $customer->name)
            ->assertJsonPath('data.employee.name', $employee->name)
            ->json('data');

        $this->putJson("/api/educational-bonus-sources/{$bonus['id']}", ['status' => 'paid'])->assertUnprocessable();
        $this->putJson("/api/educational-bonus-sources/{$bonus['id']}", ['status' => 'approved'])->assertOk();
        $this->putJson("/api/educational-bonus-sources/{$bonus['id']}", ['status' => 'paid'])->assertOk();

        $this->assertDatabaseHas('educational_bonus_sources', ['id' => $bonus['id'], 'status' => 'paid', 'source_amount' => 1000]);
    }
}
