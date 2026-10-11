<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Language;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalContractIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createContract(): array
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $currency = Currency::factory()->create(['rate' => 1]);
        $language = Language::create(['name' => 'English', 'code' => 'en']);
        $template = app(BuiltInContractTemplateService::class)->ensure('academy');
        $template->versions()->create([
            'language_id' => $language->id, 'version_number' => 1, 'name' => 'Terms',
            'status' => 'active', 'is_current' => true,
            'current_scope_key' => "{$template->id}:0:{$language->id}",
            'content' => '<p>{{customer.name}}</p>',
        ]);

        return $this->postJson('/api/contracts/academy', [
            'name' => 'Contract Case', 'fee' => 100, 'discount' => 0, 'currency_id' => $currency->id,
            'currency_rate' => 1, 'language_id' => $language->id, 'start_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.type_slug', 'customer')->json('data');
    }

    public function test_builtin_contract_links_to_its_auto_created_case(): void
    {
        $contract = $this->createContract();
        $this->getJson('/api/applicants/'.$contract['applicant_id'])->assertOk()->assertJsonPath('data.contracts.0.id', $contract['id']);
        $this->getJson('/api/contracts?service=academy')->assertOk()->assertJsonPath('data.0.id', $contract['id']);
    }

    public function test_contract_activity_blocks_applicant_delete(): void
    {
        $contract = $this->createContract();
        $this->deleteJson('/api/applicants/'.$contract['applicant_id'])->assertConflict()
            ->assertJsonPath('code', 'business.applicant_has_activity')->assertJsonPath('blocking.services', 1);
    }
}
