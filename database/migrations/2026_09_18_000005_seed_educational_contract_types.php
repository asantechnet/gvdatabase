<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Adds the agency's supported contract types without altering legacy types. */
return new class extends Migration
{
    private const TYPES = [
        ['name' => 'Educational Service', 'slug' => 'educational-service'],
        ['name' => 'Scholarship Service', 'slug' => 'scholarship-service'],
        ['name' => 'Test Service', 'slug' => 'test-service'],
        ['name' => 'Visa Service', 'slug' => 'visa-service'],
    ];

    public function up(): void
    {
        foreach (self::TYPES as $type) {
            DB::table('contract_types')->updateOrInsert(['slug' => $type['slug']], [
                ...$type, 'is_system' => true, 'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('contract_types')->whereIn('slug', array_column(self::TYPES, 'slug'))->delete();
    }
};
