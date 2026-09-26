<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicantSeeder extends Seeder
{
    /**
     * Seeds the party records that used to live in `customers`. They are now
     * plain applicants: a case is layered onto one of them by
     * EducationalSeeder, which is the same shape the cutover migration produced
     * when it folded each case-less customer into its own applicant row.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear contracted service lines before the contracts that own them.
        DB::table('contract_service')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Keep the demo party data coupled to the current service catalog.
        $services = Service::query()->orderBy('id')->take(3)->get();
        if ($services->count() < 3) {
            throw new \RuntimeException('Seed at least three services before applicants.');
        }
        [$consultingService, $trainingService, $developmentService] = $services->all();

        $john = Applicant::create([
            'name' => 'John Doe',
            'company' => 'Acme Corp',
            'contact' => '555-1234',
            'marketer' => 'Sarah Johnson',
            'date' => '2023-10-15',
        ]);

        $jane = Applicant::create([
            'name' => 'Jane Smith',
            'company' => 'Globex',
            'contact' => '555-5678',
            'marketer' => 'Michael Brown',
            'date' => '2023-10-16',
        ]);

        $bob = Applicant::create([
            'name' => 'Bob Wilson',
            'company' => 'Initech',
            'contact' => '555-9012',
            'marketer' => 'Sarah Johnson',
            'date' => '2023-10-17',
        ]);

        // Each sold service is a line on a customer contract.
        $this->contractFor($john, [$consultingService->id]);

        $this->contractFor($jane, [$trainingService->id]);

        $this->contractFor($bob, [$developmentService->id]);

        // Add an applicant with multiple services.
        $alice = Applicant::create([
            'name' => 'Alice Cooper',
            'company' => 'Wayne Enterprises',
            'contact' => '555-3456',
            'marketer' => 'Michael Brown',
            'date' => '2023-10-18',
        ]);

        $this->contractFor($alice, [$consultingService->id, $developmentService->id]);
    }

    /** @param list<int> $serviceIds */
    private function contractFor(Applicant $applicant, array $serviceIds): Contract
    {
        $contract = Contract::create([
            'contract_type_id' => ContractType::query()->where('slug', 'customer')->value('id'),
            'applicant_id' => $applicant->id,
            'name' => 'Seeded service agreement',
            // `type` and the contract_types slug stay "Customer" — see warning.md.
            'type' => 'Customer',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'counterparty' => $applicant->name,
            'counterparty_company' => $applicant->company,
            'counterparty_contact' => $applicant->contact,
            'content' => '',
            'is_custom' => true,
        ]);
        foreach ($serviceIds as $serviceId) {
            $contract->contractServices()->create(['service_id' => $serviceId]);
        }

        return $contract;
    }
}
