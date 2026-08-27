<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'HQ', 'code' => 'HQ'],
            ['name' => 'Branch A', 'code' => 'BR-A'],
            ['name' => 'Branch B', 'code' => 'BR-B'],
        ] as $office) {
            Office::withTrashed()->updateOrCreate(
                ['code' => $office['code']],
                [...$office, 'status' => 'active', 'deleted_at' => null],
            );
        }
    }
}
