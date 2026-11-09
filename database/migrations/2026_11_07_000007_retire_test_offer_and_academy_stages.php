<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test-offer and academy contracts are direct service agreements, not cases.
 * Their stages are retired together with the mirror position on applicants and
 * lines. Existing history rows stay untouched so audit and print snapshots
 * remain honest; only the live workflow is withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        $services = ['test_offer', 'academy'];

        if (Schema::hasTable('applicant_stages')) {
            DB::table('applicant_stages')
                ->whereIn('service', $services)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }

        if (Schema::hasColumn('applicants', 'current_stage_id')) {
            DB::table('applicants')
                ->whereIn('service', $services)
                ->update(['current_stage_id' => null, 'updated_at' => now()]);
        }

        if (Schema::hasColumn('applicant_contract', 'current_stage_id')) {
            DB::table('applicant_contract')
                ->whereIn('service', $services)
                ->update(['current_stage_id' => null, 'updated_at' => now()]);
        }
    }

    /** The retirement is intentionally not reversible: old history is kept. */
    public function down(): void
    {
        //
    }
};
