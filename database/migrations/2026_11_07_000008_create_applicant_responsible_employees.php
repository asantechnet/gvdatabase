<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Plural responsibility; the legacy admission_employee_id stays read-only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_responsible_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['applicant_id', 'employee_id']);
        });

        DB::table('applicants')
            ->whereNotNull('admission_employee_id')
            ->orderBy('id')
            ->chunkById(200, function ($applicants): void {
                foreach ($applicants as $applicant) {
                    DB::table('applicant_responsible_employees')->insertOrIgnore([
                        'applicant_id' => $applicant->id,
                        'employee_id' => $applicant->admission_employee_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_responsible_employees');
    }
};
