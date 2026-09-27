<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        Office::withTrashed()->updateOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Head Office', 'code' => 'HQ', 'status' => 'active', 'deleted_at' => null],
        );
    }
}
