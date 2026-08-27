<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing currencies
        Currency::query()->delete();

        // Reset auto-increment
        DB::statement('ALTER TABLE currencies AUTO_INCREMENT = 1');

        // Sample currencies
        $currencies = [
            [
                'name' => 'United States Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'rate' => 1.0,
                'is_base' => true,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'rate' => 0.92,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'British Pound',
                'code' => 'GBP',
                'symbol' => '£',
                'rate' => 0.79,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Japanese Yen',
                'code' => 'JPY',
                'symbol' => '¥',
                'rate' => 155.4,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 0,
            ],
            [
                'name' => 'Canadian Dollar',
                'code' => 'CAD',
                'symbol' => 'CA$',
                'rate' => 1.36,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Australian Dollar',
                'code' => 'AUD',
                'symbol' => 'AU$',
                'rate' => 1.51,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Swiss Franc',
                'code' => 'CHF',
                'symbol' => 'CHF',
                'rate' => 0.89,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Chinese Yuan',
                'code' => 'CNY',
                'symbol' => '¥',
                'rate' => 7.25,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Indian Rupee',
                'code' => 'INR',
                'symbol' => '₹',
                'rate' => 83.5,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Russian Ruble',
                'code' => 'RUB',
                'symbol' => '₽',
                'rate' => 90.5,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
        ];

        // Create currencies
        foreach ($currencies as $currency) {
            Currency::create($currency);
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
