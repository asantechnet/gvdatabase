<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Test-offer contracts own a case again, so their stage workflow must be live.
 *
 * The earlier retirement only deactivated the rows; it kept the keys, history
 * and transition edges. Reactivating them restores the workflow without
 * duplicating stages or rewriting any historical reference. Academy stays
 * case-free.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('applicant_stages')
            ->where('service', 'test_offer')
            ->whereNull('deleted_at')
            ->update(['is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('applicant_stages')
            ->where('service', 'test_offer')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
};
