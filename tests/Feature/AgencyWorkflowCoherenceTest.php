<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgencyWorkflowCoherenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    private function employee(string $specialty): Employee
    {
        return Employee::factory()->create([
            'position_id' => Position::where('system_key', $specialty)->value('id'),
            'status' => 'active',
        ]);
    }

    public function test_visitors_optionally_assign_active_consultants_by_position_or_role(): void
    {
        $consultant = $this->employee('counselor');
        $admission = $this->employee('admission');
        $data = ['name' => 'Visitor', 'contact' => '+93700000000', 'service' => 'Private', 'date' => '2026-10-09'];
        $this->postJson('/api/visitors', $data)->assertCreated()->assertJsonPath('data.consultant_employee_id', null);
        $created = $this->postJson('/api/visitors', [...$data, 'consultant_employee_id' => $consultant->id])
            ->assertCreated()->assertJsonPath('data.consultant.name', $consultant->name)->json('data.id');
        $this->putJson("/api/visitors/{$created}", ['consultant_employee_id' => null])->assertOk()
            ->assertJsonPath('data.consultant_employee_id', null);
        $this->postJson('/api/visitors', [...$data, 'consultant_employee_id' => $admission->id])
            ->assertUnprocessable()->assertJsonValidationErrors('consultant_employee_id');
        $consultant->update(['status' => 'inactive']);
        $this->postJson('/api/visitors', [...$data, 'consultant_employee_id' => $consultant->id])
            ->assertUnprocessable()->assertJsonValidationErrors('consultant_employee_id');
        $roleUser = User::factory()->create(['roles' => ['counselor']]);
        $roleEmployee = Employee::factory()->create(['user_id' => $roleUser->id, 'status' => 'active']);
        $this->postJson('/api/visitors', [...$data, 'consultant_employee_id' => $roleEmployee->id])->assertCreated();
    }

    public function test_a_counselor_creating_a_visitor_is_locked_to_their_own_employee(): void
    {
        $other = $this->employee('counselor');
        $roleUser = User::factory()->create(['roles' => ['counselor']]);
        $self = Employee::factory()->create([
            'user_id' => $roleUser->id,
            'status' => 'active',
            'position_id' => Position::where('system_key', 'counselor')->value('id'),
        ]);
        Sanctum::actingAs($roleUser, ['access']);

        // Even when another consultant id is submitted, the signed-in
        // consultant is recorded as the visitor's consultant.
        $this->postJson('/api/visitors', [
            'name' => 'Locked Visitor', 'contact' => '+93700000009',
            'service' => 'Private', 'date' => '2026-10-09',
            'consultant_employee_id' => $other->id,
        ])->assertCreated()->assertJsonPath('data.consultant_employee_id', $self->id);
    }

    public function test_admission_assignment_is_optional_and_independent_of_case_ownership(): void
    {
        $admission = $this->employee('admission');
        $owner = $this->employee('counselor');
        $data = ['name' => 'Applicant', 'father_name' => 'Father', 'father_phone' => '+93700000001', 'responsible_employee_id' => $owner->id];
        $id = $this->postJson('/api/applicants', [...$data, 'admission_employee_id' => $admission->id])
            ->assertCreated()->assertJsonPath('data.admission_employee.name', $admission->name)
            ->assertJsonPath('data.responsible_employee_id', $owner->id)->json('data.id');
        $this->putJson("/api/applicants/{$id}", ['admission_employee_id' => null])->assertOk()
            ->assertJsonPath('data.admission_employee_id', null);
        $this->postJson('/api/applicants', [...$data, 'admission_employee_id' => $owner->id])
            ->assertUnprocessable()->assertJsonValidationErrors('admission_employee_id');
        $this->postJson('/api/applicants', $data)->assertCreated()->assertJsonPath('data.admission_employee_id', null);
    }

    public function test_crm_staff_can_load_specialist_options_without_hr_directory_access(): void
    {
        $counselor = $this->employee('counselor');
        $this->employee('admission');
        Sanctum::actingAs(User::factory()->create(['roles' => ['staff']]), ['access']);
        $this->getJson('/api/employees/assignment-options?specialty=counselor')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $counselor->id);
        $this->getJson('/api/employees/assignment-options?specialty=marketer')->assertUnprocessable();
    }

    public function test_regular_staff_cannot_access_management_overview_or_visa_reports(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['staff']]), ['access']);
        $this->getJson('/api/educational-dashboard')->assertForbidden();
        $this->getJson('/api/educational-reports/applicants')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['roles' => ['admin']]), ['access']);
        $this->getJson('/api/educational-dashboard')->assertOk();
    }

    public function test_marketer_schema_routes_and_payloads_are_retired(): void
    {
        $this->assertFalse(Schema::hasTable('marketers'));
        foreach (['visitors', 'applicants', 'contracts', 'applicant_contract'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'marketer_id'));
        }
        $this->getJson('/api/marketers')->assertNotFound();
        $this->postJson('/api/applicants', ['name' => 'Old client', 'marketer_id' => 12])
            ->assertUnprocessable()->assertJsonValidationErrors('marketer_id');
    }

    public function test_cancelled_contract_filter_accepts_serialized_boolean_and_excludes_active_contracts(): void
    {
        Contract::factory()->create(['status' => 'cancelled', 'is_cancelled' => true]);
        Contract::factory()->create(['status' => 'active', 'is_cancelled' => false]);
        $this->getJson('/api/contracts?only_cancelled=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_current_applicant_placeholders_resolve_family_contacts_and_keep_legacy_tokens(): void
    {
        $applicant = Applicant::factory()->create(['name' => 'Student', 'father_name' => 'Father', 'father_phone' => '0701234567']);
        $content = app(PlaceholderResolver::class)->resolve('<p>{{ applicant.name }} / {{ applicant.father_name }} / {{ applicant.father_phone }} / {{ customer.name }}</p>', ['applicant' => $applicant], 'customer');
        $this->assertSame([], $content['unresolved']);
        $this->assertStringContainsString('Student / Father / 0701234567 / Student', $content['content']);
        $groups = collect(PlaceholderRegistry::groupsForSlug('customer'));
        $this->assertTrue($groups->firstWhere('key', 'customer')['deprecated']);
        $this->assertNotContains('service.duration', PlaceholderRegistry::keysForSlug('customer'));
    }

    public function test_clauses_can_be_scoped_to_service_or_employee_position(): void
    {
        $policy = ['label' => 'Private clause', 'type' => 'customer', 'service' => 'private', 'content' => '<p>Private admission terms</p>'];
        $id = $this->postJson('/api/contract-policies', $policy)->assertCreated()->assertJsonPath('data.service', 'private')->json('data.id');
        $this->putJson("/api/contract-policies/{$id}", ['type' => 'employment'])->assertUnprocessable()->assertJsonValidationErrors('service');
        $this->postJson('/api/contract-policies', [...$policy, 'service' => null, 'type' => 'employment', 'position_id' => Position::where('system_key', 'counselor')->value('id')])->assertCreated();
    }
}
