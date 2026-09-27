<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Office;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $officeId = Office::where('code', 'HQ')->value('id');

        $assets = [
            [
                'serial_number' => 'SN-1001',
                'item' => 'Dell Laptop',
                'quantity' => 1,
                'model' => 'XPS 15',
                'condition' => 'good',
                'office_id' => $officeId,
            ],
            [
                'serial_number' => 'SN-1002',
                'item' => 'HP Monitor',
                'quantity' => 2,
                'model' => '27f',
                'condition' => 'new',
                'office_id' => $officeId,
            ],
            [
                'serial_number' => 'SN-1003',
                'item' => 'Logitech Mouse',
                'quantity' => 5,
                'model' => 'MX Master',
                'condition' => 'fair',
                'office_id' => $officeId,
            ],
        ];

        foreach ($assets as $asset) {
            Asset::create($asset);
        }
    }
}
