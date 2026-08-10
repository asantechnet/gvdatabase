<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing services
        Service::query()->delete();

        // Reset auto-increment
        DB::statement('ALTER TABLE services AUTO_INCREMENT = 1');

        // Sample service types with fees
        $services = [
            ['type' => 'Consulting', 'duration' => 7, 'fee' => 1000.00],
            ['type' => 'Development', 'duration' => 30, 'fee' => 3000.00],
            ['type' => 'Support', 'duration' => 30, 'fee' => 500.00],
            ['type' => 'Maintenance', 'duration' => 30, 'fee' => 200.00],
            ['type' => 'Training', 'duration' => 5, 'fee' => 1500.00],
            ['type' => 'Design', 'duration' => 14, 'fee' => 2500.00],
            ['type' => 'Marketing', 'duration' => 30, 'fee' => 1200.00],
            ['type' => 'Hosting', 'duration' => 30, 'fee' => 100.00],
            ['type' => 'Security', 'duration' => 30, 'fee' => 800.00],
            ['type' => 'Analytics', 'duration' => 7, 'fee' => 600.00]
        ];

        // Create sample services
        foreach ($services as $service) {
            Service::create([
                'type' => $service['type'],
                'duration' => $service['duration'],
                'fee' => $service['fee'],
                'status' => 'active',
                'currency_id' => 1 // Assuming USD is currency_id 1
            ]);
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
