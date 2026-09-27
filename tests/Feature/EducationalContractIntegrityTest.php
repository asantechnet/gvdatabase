<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalContractIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_customer_contract_is_versioned_and_linked_to_its_case(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $template = ContractTemplate::create(['name' => 'Student service agreement', 'contract_type_id' => $type->id, 'service_id' => $service->id, 'cover_text' => '', 'content' => '<p>Legacy copy</p>']);
        $language = Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        $this->postJson("/api/contract-templates/{$template->id}/versions", ['language_id' => $language->id, 'content' => '<p>Educational agreement for {{applicant.name}}</p>', 'status' => 'active'])->assertCreated();
        $case = $this->postJson('/api/applicants', ['name' => 'Contract Case'])->assertCreated()->json('data');

        $contract = $this->postJson('/api/contracts', ['template_id' => $template->id, 'applicant_id' => $case['id'], 'service_ids' => [$service->id], 'language_id' => $language->id, 'status' => 'draft', 'start_date' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.applicant_id', $case['id'])->assertJsonPath('data.type_slug', 'customer')->json('data');

        $this->getJson('/api/applicants/'.$case['id'])->assertOk()->assertJsonPath('data.contracts.0.id', $contract['id']);
        $this->getJson('/api/contracts?audience=customers')->assertOk()->assertJsonPath('data.0.id', $contract['id']);
    }

    public function test_contract_activity_blocks_applicant_delete(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $template = ContractTemplate::create(['name' => 'Student service agreement', 'contract_type_id' => $type->id, 'service_id' => $service->id, 'cover_text' => '', 'content' => '<p>Terms</p>']);
        $language = Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        $this->postJson("/api/contract-templates/{$template->id}/versions", ['language_id' => $language->id, 'content' => '<p>Terms</p>', 'status' => 'active'])->assertCreated();
        $case = $this->postJson('/api/applicants', ['name' => 'Integrity Case'])->assertCreated()->json('data');

        $this->postJson('/api/contracts', ['template_id' => $template->id, 'applicant_id' => $case['id'], 'service_ids' => [$service->id], 'language_id' => $language->id, 'status' => 'draft', 'start_date' => now()->toDateString()])->assertCreated();
        $this->deleteJson('/api/applicants/'.$case['id'])->assertConflict()->assertJsonPath('code', 'business.applicant_has_activity')->assertJsonPath('blocking.services', 1);
    }
}
