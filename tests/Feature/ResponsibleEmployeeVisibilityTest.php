<?php

namespace Tests\Feature;

use App\Http\Resources\ApplicantResource;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Support\ContractAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ResponsibleEmployeeVisibilityTest extends TestCase
{
    use CreatesBuiltInContracts;
    use RefreshDatabase;

    private function admissionPosition(): Position
    {
        return Position::query()->where('system_key', 'admission')->first()
            ?? Position::factory()->create([
                'system_key' => 'admission',
                'title' => 'Admission Officer',
            ]);
    }

    private function staffEmployee(): Employee
    {
        $user = User::factory()->create([
            'acc_status' => 'active',
            'roles' => ['staff'],
        ]);

        return Employee::factory()->create([
            'user_id' => $user->id,
            'position_id' => $this->admissionPosition()->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    private function actAsSuperAdmin(): User
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    /** @return array{0: Applicant, 1: Contract} */
    private function createScholarshipCase(array $responsibleEmployeeIds): array
    {
        $this->actAsSuperAdmin();
        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_ids' => $responsibleEmployeeIds,
        ]);

        return [
            Applicant::findOrFail($created['applicant_id']),
            Contract::findOrFail($created['id']),
        ];
    }

    public function test_every_assigned_employee_can_list_and_open_the_case_and_contract(): void
    {
        $first = $this->staffEmployee();
        $second = $this->staffEmployee();
        [$applicant, $contract] = $this->createScholarshipCase([$first->id, $second->id]);

        foreach ([$first, $second] as $employee) {
            Sanctum::actingAs($employee->user, ['access']);

            $this->getJson('/api/applicants')
                ->assertOk()
                ->assertJsonPath('data.data.0.id', $applicant->id);
            $this->getJson("/api/applicants/{$applicant->id}")
                ->assertOk()
                ->assertJsonCount(2, 'data.responsible_employee_ids');

            $visibleContractIds = ContractAccess::scopeVisibleTo(Contract::query(), $employee->user)
                ->pluck('id')
                ->all();
            $this->assertContains($contract->id, $visibleContractIds);
        }

        $outsider = $this->staffEmployee();
        Sanctum::actingAs($outsider->user, ['access']);

        $this->getJson('/api/applicants')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson("/api/applicants/{$applicant->id}")->assertForbidden();
        $this->assertNotContains(
            $contract->id,
            ContractAccess::scopeVisibleTo(Contract::query(), $outsider->user)->pluck('id')->all(),
        );
    }

    public function test_removing_an_employee_revokes_their_case_and_contract_visibility(): void
    {
        $first = $this->staffEmployee();
        $second = $this->staffEmployee();
        [$applicant, $contract] = $this->createScholarshipCase([$first->id, $second->id]);

        Sanctum::actingAs($this->actAsSuperAdmin(), ['access']);
        $this->putJson("/api/applicants/{$applicant->id}", [
            'responsible_employee_ids' => [$second->id],
        ])->assertOk();

        $this->assertSame($second->id, (int) $applicant->fresh()->responsible_employee_id);

        Sanctum::actingAs($first->user, ['access']);
        $this->getJson('/api/applicants')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson("/api/applicants/{$applicant->id}")->assertForbidden();
        $this->assertNotContains(
            $contract->id,
            ContractAccess::scopeVisibleTo(Contract::query(), $first->user)->pluck('id')->all(),
        );

        Sanctum::actingAs($second->user, ['access']);
        $this->getJson('/api/applicants')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $applicant->id);
        $this->getJson("/api/applicants/{$applicant->id}")->assertOk();
        $this->assertContains(
            $contract->id,
            ContractAccess::scopeVisibleTo(Contract::query(), $second->user)->pluck('id')->all(),
        );
    }

    public function test_responsible_employee_filter_matches_every_pivot_member(): void
    {
        $first = $this->staffEmployee();
        $second = $this->staffEmployee();
        [$applicant] = $this->createScholarshipCase([$first->id, $second->id]);

        $this->actAsSuperAdmin();

        foreach ([$first, $second] as $employee) {
            $this->getJson('/api/applicants?responsible_employee_id='.$employee->id)
                ->assertOk()
                ->assertJsonCount(1, 'data.data')
                ->assertJsonPath('data.data.0.id', $applicant->id);
        }
    }

    public function test_a_legacy_singular_owner_remains_visible_and_is_serialized_as_one_assignment(): void
    {
        $employee = $this->staffEmployee();
        $applicant = Applicant::factory()->create([
            'service' => 'scholarship',
            'responsible_employee_id' => $employee->id,
        ]);

        Sanctum::actingAs($employee->user, ['access']);

        $this->getJson('/api/applicants')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $applicant->id)
            ->assertJsonPath('data.data.0.responsible_employee_ids', [$employee->id]);
        $this->getJson("/api/applicants/{$applicant->id}")
            ->assertOk()
            ->assertJsonPath('data.responsible_employee_ids', [$employee->id])
            ->assertJsonPath('data.responsible_employee.id', $employee->id);

        $payload = (new ApplicantResource(
            $applicant->fresh()->load(['responsibleEmployee', 'responsibleEmployees']),
        ))->resolve();
        $this->assertSame([$employee->id], $payload['responsible_employee_ids']);
        $this->assertSame($employee->id, $payload['responsible_employee']['id']);
    }
}
