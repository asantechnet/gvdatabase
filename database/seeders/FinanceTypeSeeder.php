<?php

namespace Database\Seeders;

use App\Models\FinanceType;
use Illuminate\Database\Seeder;

class FinanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (FinanceType::DEFAULTS as $kind => $values) {
            foreach ($values as $value) {
                FinanceType::firstOrCreate(['kind' => $kind, 'value' => $value], ['name' => $value, 'is_system' => ($kind === 'income' && in_array($value, ['Service', 'Other'], true)) || ($kind === 'expense' && in_array($value, ['Salaries', 'Pending transaction'], true))]);
            }
        }
    }
}
