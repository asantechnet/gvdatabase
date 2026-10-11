<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completion pass: adds the cross-section foreign keys whose target tables
 * are created in a later section than the referencing table.
 *
 * Each column was created (with its index) in its own section file; this
 * migration only attaches the constraints. Constraint names follow the
 * Laravel `table_column_foreign` convention, matching the historical state.
 */
return new class extends Migration
{
    public function up(): void
    {
        // HR section -> later sections
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('pending_transactions', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('applicants')->nullOnDelete();
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
        });

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->foreign('source_task_id')->references('id')->on('tasks')->nullOnDelete();
        });

    }

    public function down(): void
    {

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->dropForeign('worthy_employees_source_task_id_foreign');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropForeign('salary_payments_expense_id_foreign');
        });

        Schema::table('pending_transactions', function (Blueprint $table) {
            $table->dropForeign('pending_transactions_applicant_id_foreign');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign('departments_manager_employee_id_foreign');
        });
    }
};
