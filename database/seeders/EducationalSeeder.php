<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\ApplicantUniversity;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomerPackage;
use App\Models\EducationalBonusSource;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Package;
use App\Models\PendingTransaction;
use App\Models\Semester;
use App\Models\Service;
use App\Models\University;
use App\Services\PackageIssuanceService;
use Illuminate\Database\Seeder;

class EducationalSeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->firstOrFail();
        $employee = Employee::query()->orderBy('id')->firstOrFail();
        // Promote the first two seeded parties into cases, mirroring how the
        // cutover migration folded each case-less customer into its own row.
        $firstParty = Applicant::query()->orderBy('id')->firstOrFail();
        $secondParty = Applicant::query()->orderBy('id')->skip(1)->first() ?? $firstParty;

        $countries = collect([
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Germany', 'code' => 'DE'],
            ['name' => 'United Kingdom', 'code' => 'GB'],
            ['name' => 'Turkey', 'code' => 'TR'],
        ])->mapWithKeys(fn (array $data) => [$data['code'] => Country::withTrashed()->updateOrCreate(['code' => $data['code']], [...$data, 'is_active' => true, 'deleted_at' => null])]);

        $levels = collect([
            ['name' => 'Bachelor', 'sort_order' => 10],
            ['name' => 'Master', 'sort_order' => 20],
            ['name' => 'PhD', 'sort_order' => 30],
        ])->mapWithKeys(fn (array $data) => [$data['name'] => EducationLevel::withTrashed()->updateOrCreate(['name' => $data['name']], [...$data, 'is_active' => true, 'deleted_at' => null])]);

        $languages = collect([
            ['name' => 'English', 'code' => 'en'],
            ['name' => 'German', 'code' => 'de'],
            ['name' => 'French', 'code' => 'fr'],
        ])->mapWithKeys(fn (array $data) => [$data['code'] => Language::withTrashed()->updateOrCreate(['code' => $data['code']], [...$data, 'is_active' => true, 'deleted_at' => null])]);

        $packages = collect([
            ['name' => 'University Application Package', 'services' => ['University Application Support', 'Document Review']],
            ['name' => 'Scholarship Support Package', 'services' => ['Scholarship Application Support', 'Document Review']],
            ['name' => 'Test Preparation Package', 'services' => ['English Language Test Offer', 'Visa Interview Preparation']],
        ])->mapWithKeys(function (array $data) use ($currency) {
            $serviceIds = Service::query()->whereIn('type', $data['services'])->pluck('id');
            $package = Package::withTrashed()->updateOrCreate(['name' => $data['name']], ['is_active' => true, 'currency_id' => $currency->id, 'service_manifest' => $serviceIds->values()->all(), 'deleted_at' => null]);
            $package->services()->sync($serviceIds);

            return [$data['name'] => $package];
        });
        $semester = Semester::withTrashed()->updateOrCreate(['code' => 'FALL-2027'], ['name' => 'Fall 2027', 'sort_order' => 10, 'is_active' => true, 'deleted_at' => null]);

        $universities = collect([
            ['name' => 'University of Toronto', 'country' => 'CA', 'city' => 'Toronto'],
            ['name' => 'Technical University of Munich', 'country' => 'DE', 'city' => 'Munich'],
            ['name' => 'University of Manchester', 'country' => 'GB', 'city' => 'Manchester'],
        ])->map(fn (array $data) => University::withTrashed()->updateOrCreate(
            ['name' => $data['name'], 'country_id' => $countries[$data['country']]->id],
            ['city' => $data['city'], 'is_active' => true, 'deleted_at' => null],
        ));

        $stage = ApplicantStage::query()->where('key', 'consultation')->firstOrFail();
        $applicant = Applicant::withTrashed()->updateOrCreate(
            ['id' => $firstParty->id],
            [
                'application_date' => now()->subDays(10)->toDateString(),
                'responsible_employee_id' => $employee->id,
                'country_id' => $countries['CA']->id,
                'university_id' => $universities[0]->id,
                'education_level_id' => $levels['Master']->id,
                'language_id' => $languages['en']->id,
                'semester_id' => $semester->id,
                'current_stage_id' => $stage->id,
                'status' => 'active',
                'notes' => 'Demo educational visa case.',
                'deleted_at' => null,
            ],
        );

        ApplicantUniversity::updateOrCreate(
            ['applicant_id' => $applicant->id, 'university_id' => $universities[0]->id, 'intake' => 'Fall 2027'],
            ['semester_id' => $semester->id, 'status' => 'pending', 'notes' => 'Primary university choice.'],
        );
        ApplicantUniversity::updateOrCreate(
            ['applicant_id' => $applicant->id, 'university_id' => $universities[1]->id, 'intake' => 'Fall 2027'],
            ['semester_id' => $semester->id, 'status' => 'applied', 'notes' => 'Alternative university application.'],
        );

        ApplicantStageHistory::updateOrCreate(
            ['applicant_id' => $applicant->id, 'new_stage_id' => $stage->id],
            ['old_stage_id' => null, 'changed_by' => null, 'changed_at' => now()->subDays(10), 'note' => 'Case created from consultation.'],
        );

        $issuer = app(PackageIssuanceService::class);
        $scholarshipPurchase = CustomerPackage::withTrashed()->where('issuance_key', "applicant:{$applicant->id}:package:{$packages['Scholarship Support Package']->id}")->first();
        if (! $scholarshipPurchase) {
            $scholarshipPurchase = $issuer->issue($packages['Scholarship Support Package'], $applicant, ['sold_by_employee_id' => $employee->id, 'currency_id' => $currency->id, 'fee' => 12000, 'purchased_at' => now()->subDays(5)->toDateString()]);
        }

        // A second, smaller case so the package and bonus data is not all
        // attached to one applicant.
        $secondApplicant = Applicant::withTrashed()->updateOrCreate(
            ['id' => $secondParty->id],
            [
                'application_date' => now()->subDays(6)->toDateString(),
                'responsible_employee_id' => $employee->id,
                'country_id' => $countries['TR']->id,
                'education_level_id' => $levels['Bachelor']->id,
                'language_id' => $languages['en']->id,
                'current_stage_id' => $stage->id,
                'status' => 'active',
                'notes' => 'Demo test-preparation case.',
                'deleted_at' => null,
            ],
        );

        $testPurchase = CustomerPackage::withTrashed()->where('issuance_key', "applicant:{$secondApplicant->id}:package:{$packages['Test Preparation Package']->id}")->first();
        if (! $testPurchase) {
            $testPurchase = $issuer->issue($packages['Test Preparation Package'], $secondApplicant, ['sold_by_employee_id' => $employee->id, 'currency_id' => $currency->id, 'fee' => 5000, 'purchased_at' => now()->subDays(3)->toDateString()]);
        }
        $scholarship = $scholarshipPurchase->services()->whereHas('service', fn ($query) => $query->where('category', 'scholarship'))->firstOrFail();

        EducationalBonusSource::updateOrCreate(
            ['source_type' => $scholarship->getMorphClass(), 'source_id' => $scholarship->id, 'employee_id' => $employee->id],
            ['applicant_id' => $applicant->id, 'source_amount' => 12000, 'bonus_amount' => 600, 'status' => 'approved', 'rule_source' => 'demo educational bonus'],
        );

        PendingTransaction::withTrashed()->updateOrCreate(
            ['applicant_id' => $applicant->id, 'transaction_type' => 'income'],
            ['reason' => 'Pending educational service fee approval', 'amount' => 12000, 'currency_id' => $currency->id, 'employee_id' => $employee->id, 'approval_status' => PendingTransaction::SUBMITTED, 'requested_at' => now()->subDay(), 'deleted_at' => null],
        );
    }
}
