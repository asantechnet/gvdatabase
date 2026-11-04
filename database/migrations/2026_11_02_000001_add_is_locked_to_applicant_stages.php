<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the terminal stages that ship with the product.
 *
 * `completed`, `cancelled`, `rejected` and `withdrawn` are load-bearing: the
 * completion stage releases commission, the exit stages close a case without
 * it, and `is_terminal` drives closure, task sync and pipeline columns. If an
 * office renames, deactivates or deletes one of them, every future case is
 * silently misrouted.
 *
 * The marker is a plain boolean so a later seed only has to set it. It is not
 * fillable and never accepted from the API, so a client can neither create a
 * locked stage nor unlock one.
 *
 * The identity list comes from the two seed migrations:
 * - 0001_06_01_000006_create_reference_tables (scholarship, copied to private)
 * - 2026_10_13_000001_add_service_to_applicant_stages (test_offer, academy)
 */
return new class extends Migration
{
    /** Seeded terminal stages keyed by service. */
    private const SEEDED_TERMINAL = [
        'scholarship' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
        'private' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
        'test_offer' => ['completed', 'cancelled', 'withdrawn'],
        'academy' => ['completed', 'cancelled', 'withdrawn'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('applicant_stages', 'is_locked')) {
            Schema::table('applicant_stages', function (Blueprint $table): void {
                $table->boolean('is_locked')->default(false);
            });
        }

        // Idempotent: re-running only re-asserts the same rows, and a stage that
        // is already locked is left untouched.
        foreach (self::SEEDED_TERMINAL as $service => $keys) {
            DB::table('applicant_stages')
                ->where('service', $service)
                ->whereIn('key', $keys)
                ->where('is_terminal', true)
                ->whereNull('deleted_at')
                ->update(['is_locked' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('applicant_stages', 'is_locked')) {
            Schema::table('applicant_stages', function (Blueprint $table): void {
                $table->dropColumn('is_locked');
            });
        }
    }
};
