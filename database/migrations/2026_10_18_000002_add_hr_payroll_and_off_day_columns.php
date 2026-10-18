<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR/payroll follow-up columns:
 *  - employees.guarantor_name: a guarantor is identified by name, not only by
 *    national ID / phone / address / licence.
 *  - offices.paid_leave_days_per_year: the office base allowance an employee
 *    inherits when they have no personal override.
 *  - attendances.off_day_id: an approved holiday turns a non-worked day into a
 *    present day without pretending the employee punched in.
 *  - salaries.off_days / advance_recovery: the payslip snapshots that keep an
 *    issued payslip stable after holidays or advances change later.
 *  - employee_advances.salary_id / recovered_at: an advance is recovered once,
 *    by the payroll period that deducts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('guarantor_name', 255)->nullable()->after('guarantor_id_number');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_leave_days_per_year')->nullable()->after('geofence_enabled');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('off_day_id')->nullable()->after('status')->constrained('off_days')->nullOnDelete();
            $table->index(['off_day_id'], 'attendances_off_day_id_index');
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->decimal('off_days', 5, 1)->default(0.0)->after('paid_leave_days');
            $table->decimal('advance_recovery', 20, 8)->default(0.0)->after('bonus');
        });

        Schema::table('monthly_attendance_summaries', function (Blueprint $table) {
            $table->decimal('off_days', 5, 1)->default(0.0)->after('paid_leave_days');
        });

        Schema::table('employee_advances', function (Blueprint $table) {
            $table->foreignId('salary_id')->nullable()->after('employee_id')->constrained('salaries')->nullOnDelete();
            $table->timestamp('recovered_at')->nullable()->after('salary_id');
            $table->index(['employee_id', 'salary_id'], 'employee_advances_employee_salary_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_advances', function (Blueprint $table) {
            $table->dropIndex('employee_advances_employee_salary_index');
            $table->dropConstrainedForeignId('salary_id');
            $table->dropColumn('recovered_at');
        });

        Schema::table('monthly_attendance_summaries', function (Blueprint $table) {
            $table->dropColumn('off_days');
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn(['off_days', 'advance_recovery']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_off_day_id_index');
            $table->dropConstrainedForeignId('off_day_id');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('paid_leave_days_per_year');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('guarantor_name');
        });
    }
};
