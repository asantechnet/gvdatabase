<?php

namespace Database\Seeders;

use App\Models\Stock;
use App\Models\Office;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offices = Office::whereIn('code', ['HQ', 'BR-A', 'BR-B'])->pluck('id', 'code');

        $stocks = [
            [
                'serial_number' => 'SN-1001',
                'item' => 'Dell Laptop',
                'quantity' => 1,
                'model' => 'XPS 15',
                'condition' => 'good',
                'office_id' => $offices['HQ'],
                'staff_id' => null,
            ],
            [
                'serial_number' => 'SN-1002',
                'item' => 'HP Monitor',
                'quantity' => 2,
                'model' => '27f',
                'condition' => 'new',
                'office_id' => $offices['BR-A'],
                'staff_id' => null,
            ],
            [
                'serial_number' => 'SN-1003',
                'item' => 'Logitech Mouse',
                'quantity' => 5,
                'model' => 'MX Master',
                'condition' => 'fair',
                'office_id' => $offices['BR-B'],
                'staff_id' => null,
            ],
        ];

        foreach ($stocks as $stock) {
            Stock::create($stock);
        }
    }
}
