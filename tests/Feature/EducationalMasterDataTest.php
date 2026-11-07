<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_country_and_university_crud_are_connected(): void
    {
        $country = $this->postJson('/api/educational-master-data/countries', ['name' => 'Canada', 'code' => 'CAN'])->assertCreated()->json('data');
        $this->postJson('/api/educational-master-data/universities', ['name' => 'Example University', 'country_id' => $country['id'], 'is_active' => true])->assertCreated()->assertJsonPath('data.country.id', $country['id']);
    }

    public function test_country_and_university_contact_fields_store_typed_portal_addresses(): void
    {
        $country = $this->postJson('/api/educational-master-data/countries', [
            'name' => 'Germany',
            'code' => 'DEU',
            'phone' => '+49 30 5000 0',
            'email' => 'visa@example.test',
            // Deliberately scheme-less and path-bearing: the portal address has
            // no URL rule, so it is stored exactly as typed.
            'portal_url' => 'auswaertiges-amt.de/en/visa-service',
        ])->assertCreated()
            ->assertJsonPath('data.phone', '+49 30 5000 0')
            ->assertJsonPath('data.email', 'visa@example.test')
            ->assertJsonPath('data.portal_url', 'auswaertiges-amt.de/en/visa-service')
            ->json('data');

        $university = $this->postJson('/api/educational-master-data/universities', [
            'name' => 'Technical University',
            'country_id' => $country['id'],
            'phone' => '+49 89 289 01',
            'email' => 'admissions@example.test',
            'portal_url' => '//apply.example.test/portal?intake=Fall%202027#step-2',
        ])->assertCreated()
            ->assertJsonPath('data.phone', '+49 89 289 01')
            ->assertJsonPath('data.portal_url', '//apply.example.test/portal?intake=Fall%202027#step-2')
            ->json('data');

        $this->putJson("/api/educational-master-data/universities/{$university['id']}", [
            'portal_url' => 'https://apply.example.test/portal',
        ])->assertOk()->assertJsonPath('data.portal_url', 'https://apply.example.test/portal');

        $this->getJson('/api/educational-master-data/countries')->assertOk()
            ->assertJsonPath('data.0.phone', '+49 30 5000 0');
    }

    public function test_every_catalog_has_server_side_crud_authorization_and_reference_protection(): void
    {
        $unprivileged = User::factory()->create();
        Sanctum::actingAs($unprivileged, ['access']);
        $this->getJson('/api/educational-master-data/countries')->assertForbidden();
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($superAdmin, ['access']);

        $country = $this->postJson('/api/educational-master-data/countries', ['name' => 'Canada', 'code' => 'CAN'])->assertCreated()->json('data');
        $level = $this->postJson('/api/educational-master-data/education-levels', ['name' => 'Bachelor', 'description' => 'Undergraduate', 'sort_order' => 20])->assertCreated()->json('data');
        $language = $this->postJson('/api/educational-master-data/languages', ['name' => 'English', 'code' => 'en'])->assertCreated()->json('data');
        $university = $this->postJson('/api/educational-master-data/universities', ['name' => 'Example University', 'country_id' => $country['id'], 'city' => 'Ottawa'])->assertCreated()->json('data');

        $this->putJson("/api/educational-master-data/education-levels/{$level['id']}", ['description' => 'Four-year undergraduate'])->assertOk()->assertJsonPath('data.description', 'Four-year undergraduate');
        $this->putJson("/api/educational-master-data/languages/{$language['id']}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/educational-master-data/languages?active=1')->assertOk()->assertJsonMissing(['id' => $language['id']]);

        $applicant = Applicant::factory()->create();
        $applicant->scholarshipDetail()->create([
            'country_id' => $country['id'], 'university_id' => $university['id'],
            'education_level_id' => $level['id'],
        ]);
        $template = ContractTemplate::create(['name' => 'Admission agreement', 'contract_type_id' => ContractType::firstOrCreate(['slug' => 'educational-service'], ['name' => 'Educational service'])->id, 'cover_text' => '', 'content' => '<p>Legacy</p>']);
        ContractTemplateVersion::create(['contract_template_id' => $template->id, 'language_id' => $language['id'], 'version_number' => 1, 'content' => '<p>Version 1</p>']);

        foreach ([['countries', $country['id']], ['education-levels', $level['id']], ['languages', $language['id']], ['universities', $university['id']]] as [$catalog, $id]) {
            $this->deleteJson("/api/educational-master-data/{$catalog}/{$id}")->assertConflict()->assertJsonPath('code', 'business.master_data_in_use');
        }

        // An unreferenced catalogue row is soft deleted, then restored in place
        // by re-creating it with the same unique values.
        $unused = $this->postJson('/api/educational-master-data/semesters', ['name' => 'Spring 2030', 'code' => 'SPR-2030'])->assertCreated()->json('data');
        $this->deleteJson("/api/educational-master-data/semesters/{$unused['id']}")->assertNoContent();
        $this->assertSoftDeleted('semesters', ['id' => $unused['id']]);
        $restored = $this->postJson('/api/educational-master-data/semesters', ['name' => 'Spring 2030', 'code' => 'SPR-2030', 'sort_order' => 5])->assertCreated()->json('data');
        $this->assertSame($unused['id'], $restored['id']);
        $this->assertDatabaseHas('semesters', ['id' => $unused['id'], 'deleted_at' => null, 'sort_order' => 5]);
    }
}
