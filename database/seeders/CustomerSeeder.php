<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractType;
use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing customers
        Customer::query()->delete();

        // Reset auto-increment
        DB::statement('ALTER TABLE customers AUTO_INCREMENT = 1');

        // Clear contracted service lines
        DB::table('contract_service')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Keep the demo customer data coupled to the current service catalog.
        $services = Service::query()->orderBy('id')->take(3)->get();
        if ($services->count() < 3) {
            throw new \RuntimeException('Seed at least three services before customers.');
        }
        [$consultingService, $trainingService, $developmentService] = $services->all();

        // Create customers
        $john = Customer::create([
            'name' => 'John Doe',
            'company' => 'Acme Corp',
            'contact' => '555-1234',
            'marketer' => 'Sarah Johnson',
            'date' => '2023-10-15',
        ]);

        $jane = Customer::create([
            'name' => 'Jane Smith',
            'company' => 'Globex',
            'contact' => '555-5678',
            'marketer' => 'Michael Brown',
            'date' => '2023-10-16',
        ]);

        $bob = Customer::create([
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

        // Add a customer with multiple services
        $alice = Customer::create([
            'name' => 'Alice Cooper',
            'company' => 'Wayne Enterprises',
            'contact' => '555-3456',
            'marketer' => 'Michael Brown',
            'date' => '2023-10-18',
        ]);

        $this->contractFor($alice, [$consultingService->id, $developmentService->id]);
    }

    /** @param list<int> $serviceIds */
    private function contractFor(Customer $customer, array $serviceIds): Contract
    {
        $contract = Contract::create([
            'contract_type_id' => ContractType::query()->where('slug', 'customer')->value('id'),
            'customer_id' => $customer->id,
            'name' => 'Seeded service agreement',
            'type' => 'Customer',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'counterparty' => $customer->name,
            'counterparty_company' => $customer->company,
            'counterparty_contact' => $customer->contact,
            'content' => '',
            'is_custom' => true,
        ]);
        foreach ($serviceIds as $serviceId) {
            $contract->contractServices()->create(['service_id' => $serviceId]);
        }

        return $contract;
    }
}
