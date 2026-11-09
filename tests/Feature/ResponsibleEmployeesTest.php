<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ResponsibleEmployeesTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    private function admissionEmployee(array $attributes = []): Employee
    {
        return Employee::factory()->create([
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
            ...$attributes,
        ]);
    }

    public function test_a_case_can_assign_and_clear_two_responsible_employees(): void
    {
        $this->actingAsRole('super_admin');
        $counselor = Employee::factory()->create(['status' => 'active']);
        $first = $this->admissionEmployee();
        $second = $this->admissionEmployee();

        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_id' => $counselor->id,
            'responsible_employee_ids' => [$first->id, $second->id],
        ]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $created['responsible_employee_ids']);
        $this->assertDatabaseHas('applicant_responsible_employees', ['applicant_id' => $created['applicant_id'], 'employee_id' => $first->id]);
        $this->assertDatabaseHas('applicant_responsible_employees', ['applicant_id' => $created['applicant_id'], 'employee_id' => $second->id]);

        $this->putJson('/api/applicants/'.$created['applicant_id'], ['responsible_employee_ids' => []])
            ->assertOk()->assertJsonCount(0, 'data.responsible_employee_ids');
        $this->assertDatabaseMissing('applicant_responsible_employees', ['applicant_id' => $created['applicant_id']]);
        $this->assertDatabaseHas('applicants', ['id' => $created['applicant_id'], 'responsible_employee_id' => $counselor->id]);
    }

    public function test_the_legacy_admission_field_is_prohibited_and_the_new_permission_is_required(): void
    {
        $this->actingAsRole('super_admin');
        $admission = $this->admissionEmployee();
        $this->postJson('/api/applicants', [
            'name' => 'Legacy applicant', 'service' => 'scholarship',
            'admission_employee_id' => $admission->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('admission_employee_id');

        $counselorUser = $this->actingAsRole('counselor');
        $counselor = Employee::factory()->create([
            'user_id' => $counselorUser->id, 'status' => 'active',
        ]);
        $applicant = Applicant::factory()->create([
            'service' => 'scholarship', 'responsible_employee_id' => $counselor->id,
        ]);

        $this->putJson('/api/applicants/'.$applicant->id, [
            'responsible_employee_ids' => [$admission->id],
        ])->assertForbidden();
        $this->assertDatabaseMissing('applicant_responsible_employees', ['applicant_id' => $applicant->id]);
    }

    public function test_options_hide_responsible_employees_without_the_assignment_permission(): void
    {
        $this->actingAsRole('counselor');

        $this->getJson('/api/contracts/scholarship/options')
            ->assertOk()->assertJsonCount(0, 'data.responsible_employees');
    }
}
