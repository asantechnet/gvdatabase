<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope test-offer stages to an educational test.
 *
 * A test offer for IELTS and a test offer for TOEFL do not share one
 * workflow. `test_id` is nullable so existing stages stay a shared baseline
 * for every test; a populated value limits the stage to that test.
 *
 * Test-offer and academy stages never had a real country or university
 * dependency: the editor accepted the values but nothing consumed them.
 * They are cleared here so a stale scope cannot hide a stage from its own
 * service. The down path drops the new column and index; it cannot restore
 * the cleared scope values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applicant_stages', 'test_id')) {
            Schema::table('applicant_stages', function (Blueprint $table): void {
                $table->foreignId('test_id')->nullable()->after('service')
                    ->constrained('tests')->nullOnDelete();
            });
        }

        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->index(['service', 'test_id'], 'applicant_stages_service_test_index');
        });

        DB::table('applicant_stages')
            ->whereIn('service', ['test_offer', 'academy'])
            ->update(['country_id' => null, 'university_id' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('applicant_stages', 'test_id')) {
            return;
        }

        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->dropIndex('applicant_stages_service_test_index');
        });
        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('test_id');
        });
    }
};
