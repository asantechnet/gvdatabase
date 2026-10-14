<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stage belongs to a contracted service, not to the applicant.
 *
 * One applicant can hold several service lines at different stages, so the
 * current stage moves onto `applicant_contract` and stage history gains the
 * `applicant_contract_id` it belongs to. `applicants.current_stage_id` is kept
 * as a derived mirror for the existing aggregate screens and is updated
 * whenever a line changes stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applicant_contract', 'current_stage_id')) {
            Schema::table('applicant_contract', function (Blueprint $table): void {
                $table->foreignId('current_stage_id')
                    ->nullable()
                    ->constrained('applicant_stages')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('applicant_stage_histories', 'applicant_contract_id')) {
            Schema::table('applicant_stage_histories', function (Blueprint $table): void {
                $table->foreignId('applicant_contract_id')
                    ->nullable()
                    ->constrained('applicant_contract')
                    ->nullOnDelete();
            });
        }

        // Each existing line inherits the stage of its applicant.
        DB::table('applicant_contract')->whereNull('current_stage_id')->orderBy('id')
            ->chunkById(200, function ($lines): void {
                foreach ($lines as $line) {
                    $stage = DB::table('applicants')->where('id', $line->applicant_id)->value('current_stage_id');
                    if ($stage) {
                        DB::table('applicant_contract')->where('id', $line->id)->update(['current_stage_id' => $stage]);
                    }
                }
            });

        // Point existing history at the applicant's first line, where one exists.
        DB::table('applicant_stage_histories')->whereNull('applicant_contract_id')->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $line = DB::table('applicant_contract')->where('applicant_id', $row->applicant_id)->orderBy('id')->value('id');
                    if ($line) {
                        DB::table('applicant_stage_histories')->where('id', $row->id)->update(['applicant_contract_id' => $line]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('applicant_stage_histories', 'applicant_contract_id')) {
            Schema::table('applicant_stage_histories', function (Blueprint $table): void {
                $table->dropForeign(['applicant_contract_id']);
                $table->dropColumn('applicant_contract_id');
            });
        }

        if (Schema::hasColumn('applicant_contract', 'current_stage_id')) {
            Schema::table('applicant_contract', function (Blueprint $table): void {
                $table->dropForeign(['current_stage_id']);
                $table->dropColumn('current_stage_id');
            });
        }
    }
};
