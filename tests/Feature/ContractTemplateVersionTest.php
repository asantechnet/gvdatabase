<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractTemplateVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_versions_are_country_and_language_scoped_and_active_versions_are_immutable(): void
    {
        $type = ContractType::firstOrCreate(['slug' => 'educational-service'], ['name' => 'Educational Service']);
        $template = ContractTemplate::create(['name' => 'Admission agreement', 'contract_type_id' => $type->id, 'cover_text' => '', 'content' => '<p>legacy</p>']);
        $country = Country::create(['name' => 'Afghanistan', 'code' => 'AFG']);
        $language = Language::create(['name' => 'English', 'code' => 'en']);

        $first = $this->postJson("/api/contract-templates/{$template->id}/versions", [
            'country_id' => $country->id, 'language_id' => $language->id, 'name' => 'Admission draft', 'content' => '<p>v1</p>',
        ])->assertCreated()->json('data');
        $second = $this->postJson("/api/contract-templates/{$template->id}/versions", [
            'country_id' => $country->id, 'language_id' => $language->id, 'name' => 'Admission revision', 'content' => '<p>v2</p>',
        ])->assertCreated()->assertJsonPath('data.version_number', 2)->json('data');

        $this->putJson("/api/contract-templates/{$template->id}/versions/{$first['id']}", ['name' => 'Renamed draft'])
            ->assertOk()->assertJsonPath('data.name', 'Renamed draft');
        $this->postJson("/api/contract-templates/{$template->id}/versions/{$second['id']}/activate")
            ->assertOk()->assertJsonPath('data.is_current', true);
        $this->putJson("/api/contract-templates/{$template->id}/versions/{$second['id']}", ['name' => 'Locked'])
            ->assertStatus(422);
    }

    public function test_employment_versions_are_name_only_and_need_no_country_or_language(): void
    {
        $employment = ContractType::firstOrCreate(['slug' => 'employment'], ['name' => 'Employment']);
        $template = ContractTemplate::create([
            'name' => 'Employment Agreement — Driver', 'contract_type_id' => $employment->id,
            'cover_text' => '', 'content' => '<p>legacy</p>', 'position_id' => null,
        ]);

        $first = $this->postJson("/api/contract-templates/{$template->id}/versions", [
            'name' => 'Initial terms', 'content' => '<p>v1</p>',
        ])->assertCreated()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.country_id', null)
            ->assertJsonPath('data.test_id', null)
            ->assertJsonPath('data.language_id', null)
            ->json('data');

        $second = $this->postJson("/api/contract-templates/{$template->id}/versions", [
            'name' => 'Revised terms', 'content' => '<p>v2</p>',
        ])->assertCreated()->assertJsonPath('data.version_number', 2)->json('data');

        $this->postJson("/api/contract-templates/{$template->id}/versions/{$second['id']}/activate")
            ->assertOk()->assertJsonPath('data.is_current', true);
        $this->assertSame("{$template->id}:::", (string) ContractTemplateVersion::findOrFail($second['id'])->current_scope_key);
        $this->assertFalse(ContractTemplateVersion::findOrFail($first['id'])->is_current);

        // Every other template still has to declare a country and language scope.
        $serviceType = ContractType::firstOrCreate(['slug' => 'educational-service'], ['name' => 'Educational Service']);
        $serviceTemplate = ContractTemplate::create([
            'name' => 'Admission agreement', 'contract_type_id' => $serviceType->id, 'cover_text' => '', 'content' => '<p>legacy</p>',
        ]);
        $this->postJson("/api/contract-templates/{$serviceTemplate->id}/versions", [
            'name' => 'Missing scope', 'content' => '<p>v1</p>',
        ])->assertStatus(422);
    }
}
