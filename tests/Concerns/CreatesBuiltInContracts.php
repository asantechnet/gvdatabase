<?php

namespace Tests\Concerns;

use App\Models\Currency;
use App\Models\Language;
use App\Services\Contracts\BuiltInContractTemplateService;

trait CreatesBuiltInContracts
{
    protected function builtInContractPayload(string $service, array $data = []): array
    {
        $language = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
        $template = app(BuiltInContractTemplateService::class)->ensure($service);
        $countryId = in_array($service, ['scholarship', 'private'], true) ? ($data['country_id'] ?? null) : null;
        $version = $template->versions()->firstOrCreate([
            'current_scope_key' => "{$template->id}:".($countryId ?? 0).":{$language->id}",
        ], [
            'language_id' => $language->id, 'country_id' => $countryId, 'version_number' => 1,
            'name' => 'Active agreement', 'status' => 'active', 'is_current' => true,
            'content' => '<p>{{contract.number}} {{customer.name}} {{service.name}}</p>',
        ]);

        $payload = [...[
            'name' => 'Built-in applicant', 'fee' => 100, 'discount' => 0,
            'currency_id' => Currency::factory()->create(['rate' => 1])->id, 'currency_rate' => 1,
            'language_id' => $language->id, 'contract_template_version_id' => $version->id,
        ], ...$data];
        if (! empty($data['parent_contract_id'])) {
            unset($payload['name']);
        }

        return $payload;
    }

    protected function createBuiltInContract(string $service, array $data = []): array
    {
        return $this->postJson('/api/contracts/'.$service, $this->builtInContractPayload($service, $data))
            ->assertCreated()->json('data');
    }
}
