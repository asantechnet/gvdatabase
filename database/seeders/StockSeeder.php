<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stocks = [
            [
                'serial_number' => 'SN-1001',
                'item' => 'Dell Laptop',
                'quantity' => 1,
                'model' => 'XPS 15',
                'office' => 'HQ',
                'staff_id' => null,
            ],
            [
                'serial_number' => 'SN-1002',
                'item' => 'HP Monitor',
                'quantity' => 2,
                'model' => '27f',
                'office' => 'Branch A',
                'staff_id' => null,
            ],
            [
                'serial_number' => 'SN-1003',
                'item' => 'Logitech Mouse',
                'quantity' => 5,
                'model' => 'MX Master',
                'office' => 'Branch B',
                'staff_id' => null,
            ],
        ];

        foreach ($stocks as $stock) {
            Stock::create($stock);
        }
    }
}
