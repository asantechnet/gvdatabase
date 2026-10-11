<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Office;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $officeId = Office::query()->where('code', 'HQ')->value('id');

        $assets = [
            ['serial_number' => 'SN-1001', 'item' => 'Dell Laptop', 'quantity' => 1, 'model' => 'XPS 15', 'condition' => 'good', 'price' => 1200],
            ['serial_number' => 'SN-1002', 'item' => 'HP Monitor', 'quantity' => 2, 'model' => '27f', 'condition' => 'new', 'price' => 250],
            ['serial_number' => 'SN-1003', 'item' => 'Logitech Mouse', 'quantity' => 5, 'model' => 'MX Master', 'condition' => 'fair', 'price' => 90],
            ['serial_number' => 'SN-1004', 'item' => 'Office Printer', 'quantity' => 1, 'model' => 'LaserJet Pro', 'condition' => 'good', 'price' => 400],
            ['serial_number' => 'SN-1005', 'item' => 'Meeting Room Projector', 'quantity' => 1, 'model' => 'Epson EB', 'condition' => 'fair', 'price' => 550],
        ];

        foreach ($assets as $asset) {
            Asset::updateOrCreate(
                ['serial_number' => $asset['serial_number']],
                [...$asset, 'office_id' => $officeId],
            );
        }
    }
}
