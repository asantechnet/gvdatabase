<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A case can have more than one responsible employee. The legacy singular
 * `applicants.admission_employee_id` is copied once and kept only for old
 * payloads; the pivot is the source of truth for new reads and writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applicant_responsible_employees')) {
            Schema::create('applicant_responsible_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['applicant_id', 'employee_id'], 'applicant_responsible_unique');
                $table->index(['employee_id', 'applicant_id'], 'applicant_responsible_employee_index');
            });
        }

        if (! Schema::hasColumn('applicants', 'admission_employee_id')) {
            return;
        }

        DB::table('applicants')
            ->whereNotNull('admission_employee_id')
            ->orderBy('id')
            ->select(['id', 'admission_employee_id'])
            ->chunkById(500, function ($rows): void {
                $now = now();
                $records = [];
                foreach ($rows as $row) {
                    $records[] = [
                        'applicant_id' => $row->id,
                        'employee_id' => $row->admission_employee_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($records !== []) {
                    DB::table('applicant_responsible_employees')->insertOrIgnore($records);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_responsible_employees');
    }
};
