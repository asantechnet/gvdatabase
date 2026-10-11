<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::table('applicants')->where('service', 'scholarship')->orderBy('id')->chunkById(500, function ($applicants) {
            foreach ($applicants as $applicant) {
                // Existing detail rows are authoritative, including explicit nulls.
                if (DB::table('scholarship_applicant_details')->where('applicant_id', $applicant->id)->exists()) {
                    continue;
                }
                DB::table('scholarship_applicant_details')->insert([
                    'applicant_id' => $applicant->id,
                    'country_id' => $applicant->country_id,
                    'university_id' => $applicant->university_id,
                    'education_level_id' => $applicant->education_level_id,
                    'semester_id' => $applicant->semester_id,
                    'grade_score' => $applicant->percentage,
                    'created_at' => $applicant->created_at,
                    'updated_at' => $applicant->updated_at,
                    'deleted_at' => null,
                ]);
            }
        });

        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('applicants', function (Blueprint $table) {
                foreach (['country_id', 'university_id', 'education_level_id', 'language_id', 'semester_id'] as $column) {
                    $table->dropForeign([$column]);
                }
                $table->dropIndex('applicants_semester_id_index');
                $table->dropColumn(['country_id', 'university_id', 'education_level_id', 'percentage', 'language_id', 'semester_id', 'application_date', 'date']);
            });
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('applicants', function (Blueprint $table) {
                $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('percentage', 5, 2)->nullable();
                $table->date('application_date')->nullable();
                $table->date('date')->nullable();
                $table->index('semester_id', 'applicants_semester_id_index');
            });
        });
        DB::table('scholarship_applicant_details')->orderBy('id')->chunkById(500, function ($details) {
            foreach ($details as $detail) {
                DB::table('applicants')->where('id', $detail->applicant_id)->where('service', 'scholarship')->update([
                    'country_id' => $detail->country_id, 'university_id' => $detail->university_id,
                    'education_level_id' => $detail->education_level_id, 'semester_id' => $detail->semester_id,
                    'percentage' => $detail->grade_score !== null && $detail->grade_score <= 100 ? $detail->grade_score : null,
                ]);
            }
        });
    }
};
