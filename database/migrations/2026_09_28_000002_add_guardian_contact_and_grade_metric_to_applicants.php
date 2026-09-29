<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Family contact and academic grade metric on the applicant record.
 *
 * `father_name` / `father_phone` are the guardian contact the firm calls about
 * documents, fees and interview dates, so they live next to the applicant's own
 * name and contact. `percentage` is the applicant's educational grade metric
 * (for example a 78.50% high-school result); it is deliberately separate from
 * `education_level_id`, which only names the qualification that was achieved.
 *
 * All three are nullable: an intake record can be captured before the family
 * details and transcript are collected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('name');
            $table->string('father_phone', 50)->nullable()->after('father_name');
            $table->decimal('percentage', 5, 2)->nullable()->after('education_level_id');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['father_name', 'father_phone', 'percentage']);
        });
    }
};
