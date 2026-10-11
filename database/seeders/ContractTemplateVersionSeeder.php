<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Country;
use App\Models\Language;
use App\Models\Test;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Support\BuiltInService;
use Illuminate\Database\Seeder;

class ContractTemplateVersionSeeder extends Seeder
{
    /**
     * Ensure each built-in contract template has at least one active,
     * current version per language (scholarship/private per country+language,
     * test_offer per test+language). Academy versions are handled by
     * AcademyContractTemplateSeeder which runs earlier.
     */
    public function run(): void
    {
        $languages = Language::query()->where('is_active', true)->get();
        if ($languages->isEmpty()) {
            return;
        }

        $countries = Country::query()->where('is_active', true)->get();
        $defaultCountry = $countries->first()?->id;

        $tests = Test::query()->where('is_active', true)->get();
        $defaultTest = $tests->first()?->id;

        $services = [
            BuiltInService::PRIVATE => ['type' => 'country', 'defaultScope' => $defaultCountry],
            BuiltInService::SCHOLARSHIP => ['type' => 'country', 'defaultScope' => $defaultCountry],
            BuiltInService::TEST_OFFER => ['type' => 'test', 'defaultScope' => $defaultTest],
        ];

        foreach ($services as $service => $config) {
            $template = app(BuiltInContractTemplateService::class)->ensure($service);

            foreach ($languages as $language) {
                $countryId = null;
                $testId = null;

                if ($config['type'] === 'country') {
                    $countryId = $config['defaultScope'];
                } elseif ($config['type'] === 'test') {
                    $testId = $config['defaultScope'];
                }

                $version = ContractTemplateVersion::withTrashed()->firstOrNew([
                    'contract_template_id' => $template->id,
                    'country_id' => $countryId,
                    'test_id' => $testId,
                    'language_id' => $language->id,
                    'version_number' => 1,
                ]);

                $version->fill([
                    'name' => BuiltInService::label($service).' — '.$language->name,
                    'content' => $this->content($service),
                    'status' => 'active',
                    'is_current' => true,
                ]);
                $version->deleted_at = null;
                $version->current_scope_key = null;
                $version->save();
                $version->current_scope_key = $version->scopeKey();
                $version->save();
            }
        }
    }

    private function content(string $service): string
    {
        $serviceName = BuiltInService::label($service);

        return "<p>This agreement covers the {$serviceName} service confirmed by the firm, including the agreed terms, fees and payment schedule recorded on the applicant&rsquo;s contract.</p>"
            .'<p>All rights and obligations are governed by the contract terms and applicable law.</p>';
    }
}