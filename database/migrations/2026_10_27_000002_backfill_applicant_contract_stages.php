<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair lines whose per-service stage was never set.
 *
 * The 2026-10-13 move of the stage onto `applicant_contract` backfilled the
 * rows that existed at that moment, but demo/legacy seeders kept inserting
 * lines without a stage. A line with a NULL stage is invisible to the service
 * pipeline and offers no move on the applicant case, so every such line is
 * seated on its service's `new` stage (or, failing that, the applicant's own
 * mirrored stage).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applicant_contract', 'current_stage_id')) {
            return;
        }

        DB::table('applicant_contract')
            ->whereNull('current_stage_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($lines): void {
                foreach ($lines as $line) {
                    $stageId = null;

                    if ($line->service) {
                        $stageId = DB::table('applicant_stages')
                            ->where('key', 'new')
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                            ->where('service', $line->service)
                            ->orderByRaw('country_id IS NULL')
                            ->orderByRaw('university_id IS NULL')
                            ->value('id');
                    }

                    $stageId ??= DB::table('applicants')->where('id', $line->applicant_id)->value('current_stage_id');

                    if ($stageId) {
                        DB::table('applicant_contract')->where('id', $line->id)->update(['current_stage_id' => $stageId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data repair only: the previous NULL cannot be identified afterwards.
    }
};
