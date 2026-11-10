<?php

namespace Database\Seeders;

use App\Models\ContractTemplateVersion;
use App\Models\Language;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Services\Contracts\ContractGenerationService;
use App\Support\BuiltInService;
use Illuminate\Database\Seeder;

class AcademyContractTemplateSeeder extends Seeder
{
    /**
     * Ensure the hidden academy contract template exists with one active,
     * current version per active language.
     *
     * Academy still creates a normal contract, but it is not offered in
     * Templates & Policies, so this seeder guarantees
     * {@see ContractGenerationService} can never abort
     * for a missing academy template on a fresh install. The body is static
     * (no placeholders) so rendering can never fail on unresolved tokens.
     */
    public function run(): void
    {
        $template = app(BuiltInContractTemplateService::class)->ensure(BuiltInService::ACADEMY);
        // Explicitly hidden from the admin template list.
        $template->forceFill(['is_active' => false])->save();

        foreach (Language::query()->where('is_active', true)->get() as $language) {
            $version = ContractTemplateVersion::withTrashed()->firstOrNew([
                'contract_template_id' => $template->id,
                'country_id' => null,
                'test_id' => null,
                'language_id' => $language->id,
                'version_number' => 1,
            ]);
            $version->fill([
                'name' => 'Academy enrolment — '.$language->name,
                'content' => $this->content(),
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

    private function content(): string
    {
        return '<p>This agreement covers the academy enrolment confirmed by the firm, including the agreed program, schedule and class, for the fee and payment terms recorded on the applicant&rsquo;s contract.</p>'
            .'<p>This enrolment runs from {contract.start_date} to {contract.end_date}.</p>'
            .'<p>Attendance, conduct and completion are governed by the academy policy in force at the time of enrolment.</p>';
    }
}
