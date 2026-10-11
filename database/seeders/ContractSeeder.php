<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Language;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Services\Contracts\ContractGenerationService;
use App\Support\BuiltInService;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * One issued contract per seeded applicant, each with its service line.
     *
     * This mirrors what {@see ContractGenerationService}
     * produces: a primary patient contract bound to the built-in template and
     * a single `applicant_contract` line that carries the fee, currency and
     * payment state. Payment overrides are used deliberately so the demo set
     * shows paid, partially-paid and unpaid lines.
     */
    public function run(): void
    {
        $customerType = ContractType::query()->where('slug', 'customer')->first();
        $currency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->first();
        $language = Language::query()->where('code', 'en')->first();
        if (! $customerType || ! $currency || ! $language) {
            return;
        }

        $soldBy = Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'counselor'))->value('id');
        $templates = app(BuiltInContractTemplateService::class);

        $plans = [
            BuiltInService::SCHOLARSHIP => ['fee' => 50000, 'paid' => 50000],
            BuiltInService::PRIVATE => ['fee' => 80000, 'paid' => 30000],
            BuiltInService::TEST_OFFER => ['fee' => 15000, 'paid' => 0],
            BuiltInService::ACADEMY => ['fee' => 20000, 'paid' => 20000],
        ];

        foreach ($plans as $service => $plan) {
            $applicant = Applicant::query()->where('service', $service)->orderBy('id')->first();
            if (! $applicant) {
                continue;
            }

            $template = $templates->ensure($service);
            $contract = Contract::withTrashed()->firstOrNew([
                'applicant_id' => $applicant->id,
                'service' => $service,
                'contract_role' => Contract::ROLE_PRIMARY,
            ]);
            $contract->fill([
                'template_id' => $template->id,
                'contract_type_id' => $customerType->id,
                'contract_language_id' => $language->id,
                'sold_by_employee_id' => $soldBy,
                'name' => $template->name,
                'type' => $customerType->name,
                'cover_text' => $template->cover_text,
                'content' => $template->content,
                'status' => 'active',
                'start_date' => now()->subDays(30)->toDateString(),
                'counterparty' => $applicant->name,
                'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact,
                'counterparty_address' => $applicant->address,
                'is_custom' => false,
                'is_cancelled' => false,
            ]);
            $contract->deleted_at = null;
            $contract->save();

            $line = ApplicantContract::withTrashed()->firstOrNew([
                'contract_id' => $contract->id,
                'service' => $service,
            ]);
            $line->fill([
                'applicant_id' => $applicant->id,
                'fee' => $plan['fee'],
                'discount' => 0,
                'currency_id' => $currency->id,
                'currency_rate' => 1,
                'sold_by_employee_id' => $soldBy,
                'notes' => 'Seeded service line',
            ]);
            if ($plan['paid'] > 0) {
                $line->amount_paid_overridden = true;
                $line->amount_paid = $plan['paid'];
            }
            $line->deleted_at = null;
            $line->save();

            // Create an extension contract for each primary contract
            $extContract = Contract::withTrashed()->firstOrNew([
                'applicant_id' => $applicant->id,
                'service' => $service,
                'contract_role' => Contract::ROLE_EXTENSION,
                'parent_contract_id' => $contract->id,
            ]);
            $extContract->fill([
                'template_id' => $template->id,
                'contract_type_id' => $customerType->id,
                'contract_language_id' => $language->id,
                'sold_by_employee_id' => $soldBy,
                'name' => $template->name . ' (Extension)',
                'type' => $customerType->name,
                'cover_text' => $template->cover_text,
                'content' => $template->content,
                'status' => 'active',
                'start_date' => now()->addDays(30)->toDateString(),
                'counterparty' => $applicant->name,
                'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact,
                'counterparty_address' => $applicant->address,
                'is_custom' => false,
                'is_cancelled' => false,
            ]);
            $extContract->deleted_at = null;
            $extContract->save();

            $extLine = ApplicantContract::withTrashed()->firstOrNew([
                'contract_id' => $extContract->id,
                'service' => $service,
            ]);
            $extLine->fill([
                'applicant_id' => $applicant->id,
                'fee' => $plan['fee'],
                'discount' => 0,
                'currency_id' => $currency->id,
                'currency_rate' => 1,
                'sold_by_employee_id' => $soldBy,
                'notes' => 'Seeded extension service line',
            ]);
            $extLine->deleted_at = null;
            $extLine->save();
        }
    }
}
