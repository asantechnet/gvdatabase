<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Service;
use Illuminate\Database\Seeder;
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

        $baseCurrencyId = Currency::query()->where('is_base', true)->value('id')
            ?? throw new \RuntimeException('Seed the base currency before services.');

        // Practical starter catalog for an educational visa agency. Fees are
        // deliberately modest AFN defaults and remain editable by authorized staff.
        $services = [
            ['type' => 'Initial Education Consultation', 'category' => 'general', 'fee' => 1500.00],
            ['type' => 'Document Review', 'category' => 'general', 'fee' => 5000.00],
            ['type' => 'University Application Support', 'category' => 'general', 'fee' => 15000.00],
            ['type' => 'Scholarship Application Support', 'category' => 'scholarship', 'fee' => 12000.00],
            ['type' => 'English Language Test Offer', 'category' => 'test_offer', 'fee' => 5000.00],
            ['type' => 'Student Visa Application Support', 'category' => 'general', 'fee' => 20000.00],
            ['type' => 'Statement of Purpose and CV Review', 'category' => 'general', 'fee' => 8000.00],
            ['type' => 'Visa Interview Preparation', 'category' => 'general', 'fee' => 5000.00],
            ['type' => 'Translation Coordination', 'category' => 'general', 'fee' => 3000.00],
        ];

        // Create sample services
        foreach ($services as $service) {
            Service::create([
                'type' => $service['type'],
                'category' => $service['category'],
                'fee' => $service['fee'],
                'status' => 'active',
                'currency_id' => $baseCurrencyId,
            ]);
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
