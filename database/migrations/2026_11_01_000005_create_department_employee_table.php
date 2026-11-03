<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive department membership.
 *
 * `employees.department_id` stays as the primary department so every existing
 * reader, notification route and legacy form keeps working. The pivot adds the
 * remaining memberships and marks which one the legacy column projects.
 *
 * The pair is the primary key instead of an auto-increment id: joined queries
 * in the existing department screens address `id` unqualified, and a pivot id
 * would make both the select and the where clause ambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_employee', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['employee_id', 'department_id']);
            $table->index(['department_id', 'is_primary']);
        });

        // Backfill one primary membership per existing primary department.
        // insertOrIgnore keeps a re-run idempotent.
        DB::table('employees')
            ->whereNull('deleted_at')
            ->whereNotNull('department_id')
            ->chunkById(500, function ($employees): void {
                $now = now();
                $rows = [];

                foreach ($employees as $employee) {
                    $rows[] = [
                        'employee_id' => $employee->id,
                        'department_id' => $employee->department_id,
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('department_employee')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_employee');
    }
};
