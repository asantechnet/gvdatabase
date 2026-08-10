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
                'rate_against_base' => 1.0,
                'is_base' => true,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'rate_against_base' => 0.92,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'British Pound',
                'code' => 'GBP',
                'symbol' => '£',
                'rate_against_base' => 0.79,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Japanese Yen',
                'code' => 'JPY',
                'symbol' => '¥',
                'rate_against_base' => 155.4,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 0,
            ],
            [
                'name' => 'Canadian Dollar',
                'code' => 'CAD',
                'symbol' => 'CA$',
                'rate_against_base' => 1.36,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Australian Dollar',
                'code' => 'AUD',
                'symbol' => 'AU$',
                'rate_against_base' => 1.51,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Swiss Franc',
                'code' => 'CHF',
                'symbol' => 'CHF',
                'rate_against_base' => 0.89,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Chinese Yuan',
                'code' => 'CNY',
                'symbol' => '¥',
                'rate_against_base' => 7.25,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Indian Rupee',
                'code' => 'INR',
                'symbol' => '₹',
                'rate_against_base' => 83.5,
                'is_base' => false,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Russian Ruble',
                'code' => 'RUB',
                'symbol' => '₽',
                'rate_against_base' => 90.5,
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
