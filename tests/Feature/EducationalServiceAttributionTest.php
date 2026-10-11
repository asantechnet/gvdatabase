<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class EducationalServiceAttributionTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_builtin_line_and_bonus_credit_belong_to_the_created_applicant_and_actual_seller(): void
    {
        $employee = Employee::factory()->create(['user_id' => auth()->id()]);
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Attribution applicant', 'fee' => 500]);
        $line = ApplicantContract::where('contract_id', $contract['id'])->sole();
        $this->assertSame($contract['applicant_id'], $line->applicant_id);
        $this->assertSame($employee->id, $line->sold_by_employee_id);
        $this->postJson('/api/educational-bonus-sources', [
            'source_type' => 'service', 'source_id' => $line->id, 'bonus_amount' => 75,
            'rule_source' => 'Referral policy',
        ])->assertCreated()->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.applicant.id', $contract['applicant_id'])
            ->assertJsonPath('data.source_amount', '500.000000');
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
