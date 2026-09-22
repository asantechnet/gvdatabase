<?php

namespace Database\Seeders;

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

        // Clear pivot table
        DB::table('customer_service')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Keep the demo customer data coupled to the current service catalog.
        // The old seeder expected legacy Consulting/Training/Development rows,
        // but the application now ships with an educational service catalog.
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

        // Pricing and currency remain authoritative on the assigned service.
        $john->services()->attach($consultingService->id, [
            'deadline' => now()->addMonthsNoOverflow($consultingService->duration)->toDateString(),
        ]);

        $jane->services()->attach($trainingService->id, [
            'deadline' => now()->addMonthsNoOverflow($trainingService->duration)->toDateString(),
        ]);

        $bob->services()->attach($developmentService->id, [
            'deadline' => now()->addMonthsNoOverflow($developmentService->duration)->toDateString(),
        ]);

        // Add a customer with multiple services
        $alice = Customer::create([
            'name' => 'Alice Cooper',
            'company' => 'Wayne Enterprises',
            'contact' => '555-3456',
            'marketer' => 'Michael Brown',
            'date' => '2023-10-18',
        ]);

        $alice->services()->attach($consultingService->id, [
            'deadline' => now()->addMonthsNoOverflow($consultingService->duration)->toDateString(),
        ]);

        $alice->services()->attach($developmentService->id, [
            'deadline' => now()->addMonthsNoOverflow($developmentService->duration)->toDateString(),
        ]);
    }
}
