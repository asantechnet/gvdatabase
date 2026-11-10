<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VersionedContractGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_employment_contract_uses_its_current_version_and_keeps_snapshot(): void
    {
        $type = ContractType::firstOrCreate(['slug' => 'employment'], ['name' => 'Employment']);
        $template = ContractTemplate::create(['name' => 'Employment agreement', 'contract_type_id' => $type->id, 'cover_text' => '', 'content' => '<p>old</p>']);
        $employee = Employee::factory()->create(['name' => 'Maryam Ahmadi']);
        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id, 'name' => 'Current terms', 'version_number' => 1,
            'content' => '<p>{{employee.name}} employment</p>', 'status' => 'active', 'is_current' => true,
            'current_scope_key' => "{$template->id}:::",
        ]);

        $this->postJson('/api/contracts', [
            'template_id' => $template->id, 'employee_id' => $employee->id,
            'status' => 'active', 'start_date' => '2026-09-17',
        ])->assertCreated()
            ->assertJsonPath('data.contract_template_version_id', $version->id)
            ->assertJsonPath('data.snapshot_data.template_version.name', 'Current terms')
            ->assertJsonPath('data.content', '<p>Maryam Ahmadi employment</p>');
    }

    public function test_other_contracts_ignore_legacy_versions_and_use_the_template_document(): void
    {
        $type = ContractType::where('slug', 'other')->firstOrFail();
        $template = ContractTemplate::create([
            'name' => 'Agreement', 'contract_type_id' => $type->id,
            'cover_text' => '', 'content' => '<p>{{customer.name}} current</p>',
        ]);
        // Versions created before other agreements stopped versioning must not
        // be selected or required when a new contract is issued.
        ContractTemplateVersion::create([
            'contract_template_id' => $template->id, 'name' => 'Legacy terms', 'version_number' => 1,
            'content' => '<p>{{customer.name}} legacy</p>', 'status' => 'active', 'is_current' => true,
            'current_scope_key' => "{$template->id}:1:1",
        ]);
        $applicant = Applicant::factory()->create(['name' => 'Maryam Ahmadi']);
        $this->seedApplicantDocuments($applicant);

        $this->postJson('/api/contracts', [
            'template_id' => $template->id, 'applicant_id' => $applicant->id,
            'status' => 'active', 'start_date' => '2026-09-17',
        ])->assertCreated()
            ->assertJsonPath('data.contract_template_version_id', null)
            ->assertJsonPath('data.content', '<p>Maryam Ahmadi current</p>');
    }
}
