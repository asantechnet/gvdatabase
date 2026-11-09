<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\CommissionAccrual;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ResponsibleEmployeeAssignmentTest extends TestCase
{
    use CreatesBuiltInContracts;
    use RefreshDatabase;

    private function admissionEmployee(): Employee
    {
        $position = Position::query()->where('system_key', 'admission')->first()
            ?? Position::factory()->create([
                'system_key' => 'admission',
                'title' => 'Admission Officer',
            ]);

        return Employee::factory()->create([
            'position_id' => $position->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    private function permissionHolders(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $ids = collect($permissions)->map(fn (string $name) => Permission::firstOrCreate(
            ['name' => $name],
            ['category' => 'customer_contracts', 'guard_name' => 'api'],
        )->id);
        $role->permissions()->sync($ids->all());
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_two_responsible_employees_are_synced_once_and_never_written_to_the_legacy_column(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $first = $this->admissionEmployee();
        $second = $this->admissionEmployee();
        $payload = $this->builtInContractPayload('scholarship', [
            'responsible_employee_ids' => [$first->id, $second->id],
        ]);

        $id = $this->postJson('/api/contracts/scholarship', $payload)->assertCreated()->json('data.id');
        $applicant = Applicant::findOrFail(Contract::findOrFail($id)->applicant_id);

        $this->assertNull($applicant->admission_employee_id);
        $this->assertSame(2, DB::table('applicant_responsible_employees')->where('applicant_id', $applicant->id)->count());

        $this->putJson("/api/contracts/scholarship/{$id}", $payload)->assertOk();

        $this->assertSame(2, DB::table('applicant_responsible_employees')->where('applicant_id', $applicant->id)->count());
        $this->assertNull($applicant->fresh()->admission_employee_id);
    }

    public function test_permission_is_required_before_any_write(): void
    {
        $user = $this->permissionHolders('contract_creator', [
            'create_contracts', 'view_contracts', 'view_scholarship_contracts',
        ]);
        Sanctum::actingAs($user, ['access']);
        $employee = $this->admissionEmployee();

        $this->postJson('/api/contracts/scholarship', $this->builtInContractPayload('scholarship', [
            'responsible_employee_ids' => [$employee->id],
        ]))->assertForbidden();

        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicants', 0);
        $this->assertDatabaseCount('applicant_responsible_employees', 0);
    }

    public function test_case_free_services_reject_the_plural_field(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $employee = $this->admissionEmployee();

        foreach (['test_offer', 'academy'] as $service) {
            $this->postJson('/api/contracts/'.$service, $this->builtInContractPayload($service, [
                'responsible_employee_ids' => [$employee->id],
            ]))->assertUnprocessable()->assertJsonValidationErrors('responsible_employee_ids');
        }

        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicant_responsible_employees', 0);
    }

    public function test_ids_outside_the_active_admission_scope_are_refused(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $wrongScope = Employee::factory()->create(['status' => Employee::STATUS_ACTIVE]);

        $this->postJson('/api/contracts/scholarship', $this->builtInContractPayload('scholarship', [
            'responsible_employee_ids' => [$wrongScope->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('responsible_employee_ids.0');

        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_commission_still_comes_only_from_seller_fields_with_two_responsible_employees(): void
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        $seller = Employee::factory()->create([
            'user_id' => $user->id,
            'is_commission_based' => true,
            'status' => Employee::STATUS_ACTIVE,
        ]);
        $seller->serviceCommissions()->create(['service' => 'scholarship', 'percentage' => 50]);
        Sanctum::actingAs($user, ['access']);
        Currency::factory()->create(['code' => 'BASE', 'rate' => 1, 'is_base' => true]);
        $firstResponsible = $this->admissionEmployee();
        $secondResponsible = $this->admissionEmployee();
        $completed = ApplicantStage::query()
            ->where('service', 'scholarship')
            ->where('key', ApplicantStage::COMPLETED_KEY)
            ->firstOrFail();

        $one = Contract::findOrFail($this->createBuiltInContract('scholarship', [
            'fee' => 100,
            'discount' => 0,
            'application_fee' => 100,
            'responsible_employee_ids' => [$firstResponsible->id],
        ])['id']);
        $two = Contract::findOrFail($this->createBuiltInContract('scholarship', [
            'fee' => 100,
            'discount' => 0,
            'application_fee' => 100,
            'responsible_employee_ids' => [$firstResponsible->id, $secondResponsible->id],
        ])['id']);

        foreach ([$one, $two] as $contract) {
            $applicant = $contract->applicant;
            $applicant->forceFill(['current_stage_id' => $completed->id])->save();
            app(CommissionService::class)->syncForApplicant($applicant->fresh());
        }

        $oneAccruals = CommissionAccrual::where('contract_id', $one->id)->get();
        $twoAccruals = CommissionAccrual::where('contract_id', $two->id)->get();

        $this->assertSame(1, $oneAccruals->count());
        $this->assertSame(1, $twoAccruals->count());
        $this->assertSame([50.0], $oneAccruals->pluck('commission_base_amount')->map(fn ($v) => (float) $v)->all());
        $this->assertSame([50.0], $twoAccruals->pluck('commission_base_amount')->map(fn ($v) => (float) $v)->all());
        $this->assertSame($seller->id, (int) $oneAccruals->first()->employee_id);
        $this->assertSame($seller->id, (int) $twoAccruals->first()->employee_id);
    }
}
