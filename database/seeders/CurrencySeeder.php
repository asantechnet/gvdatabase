<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // AFN is the safe operational default for an Afghanistan-based firm.
        // Foreign currencies start inactive because their rates must be reviewed
        // by finance before they can be used for immutable transaction snapshots.
        $currencies = [
            [
                'name' => 'Afghan Afghani',
                'code' => 'AFN',
                'symbol' => '؋',
                'rate' => 1.0,
                'is_base' => true,
                'is_active' => true,
                'decimal_places' => 2,
            ],
            [
                'name' => 'United States Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'rate' => 0.014,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'rate' => 0.012,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'British Pound',
                'code' => 'GBP',
                'symbol' => '£',
                'rate' => 0.010,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Japanese Yen',
                'code' => 'JPY',
                'symbol' => '¥',
                'rate' => 2.16,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 0,
            ],
            [
                'name' => 'Canadian Dollar',
                'code' => 'CAD',
                'symbol' => 'CA$',
                'rate' => 0.019,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Australian Dollar',
                'code' => 'AUD',
                'symbol' => 'AU$',
                'rate' => 0.021,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Swiss Franc',
                'code' => 'CHF',
                'symbol' => 'CHF',
                'rate' => 0.011,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Chinese Yuan',
                'code' => 'CNY',
                'symbol' => '¥',
                'rate' => 0.099,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Indian Rupee',
                'code' => 'INR',
                'symbol' => '₹',
                'rate' => 1.17,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
            [
                'name' => 'Russian Ruble',
                'code' => 'RUB',
                'symbol' => '₽',
                'rate' => 1.15,
                'is_base' => false,
                'is_active' => false,
                'decimal_places' => 2,
            ],
        ];

        $hasBase = Currency::query()->where('is_base', true)->exists();
        foreach ($currencies as $currency) {
            if ($currency['is_base'] && $hasBase) {
                $currency['is_base'] = false;
                $currency['is_active'] = false;
            }
            $record = Currency::withTrashed()->firstOrCreate(['code' => $currency['code']], $currency);
            $hasBase = $hasBase || ($record->is_base && ! $record->trashed());
        }
    }
}
