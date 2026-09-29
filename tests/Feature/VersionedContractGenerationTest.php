<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Applicant;
use App\Models\Language;
use App\Models\Role;
use App\Models\Service;
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

    public function test_contract_uses_the_selected_active_country_and_language_version_and_keeps_snapshot(): void
    {
        $type = ContractType::where('slug', 'customer')->firstOrFail();
        $template = ContractTemplate::create(['name' => 'Agreement', 'contract_type_id' => $type->id, 'cover_text' => '', 'content' => '<p>old</p>']);
        $country = Country::create(['name' => 'Afghanistan', 'code' => 'AFG']);
        $english = Language::create(['name' => 'English', 'code' => 'en']);
        $persian = Language::create(['name' => 'Persian', 'code' => 'fa']);
        ContractTemplateVersion::create(['contract_template_id' => $template->id, 'country_id' => $country->id, 'language_id' => $english->id, 'name' => 'English agreement', 'version_number' => 1, 'content' => '<p>{{customer.name}} English</p>', 'status' => 'active', 'is_current' => true, 'current_scope_key' => "{$template->id}:{$country->id}:{$english->id}"]);
        $persianVersion = ContractTemplateVersion::create(['contract_template_id' => $template->id, 'country_id' => $country->id, 'language_id' => $persian->id, 'name' => 'Persian agreement', 'version_number' => 1, 'content' => '<p>{{customer.name}} Persian</p>', 'status' => 'active', 'is_current' => true, 'current_scope_key' => "{$template->id}:{$country->id}:{$persian->id}"]);
        $applicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($applicant);
        $service = Service::factory()->create();

        $this->postJson('/api/contracts', ['template_id' => $template->id, 'applicant_id' => $applicant->id, 'country_id' => $country->id, 'language_id' => $persian->id, 'service_ids' => [$service->id], 'status' => 'active', 'start_date' => '2026-09-17'])
            ->assertCreated()->assertJsonPath('data.contract_template_version_id', $persianVersion->id)
            ->assertJsonPath('data.snapshot_data.template_version.name', 'Persian agreement')
            ->assertJsonPath('data.content', "<p>{$applicant->name} Persian</p>");
        $this->postJson('/api/contracts', ['template_id' => $template->id, 'applicant_id' => $applicant->id, 'country_id' => $country->id, 'status' => 'active', 'start_date' => '2026-09-17'])
            ->assertUnprocessable();
    }
}
