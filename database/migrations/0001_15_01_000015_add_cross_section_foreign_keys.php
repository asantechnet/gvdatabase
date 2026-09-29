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

        // CRM section -> applicant section
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('applicants')->nullOnDelete();
        });

        // Contract section -> finance/commerce sections
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        Schema::table('contract_document_designs', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });

        Schema::table('contract_service', function (Blueprint $table) {
            $table->foreign('customer_package_id')->references('id')->on('customer_packages')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('source_service_id')->references('id')->on('services')->nullOnDelete();
        });

        // Finance section -> task management section
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign('expenses_project_id_foreign');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign('contracts_source_service_id_foreign');
        });

        Schema::table('contract_service', function (Blueprint $table) {
            $table->dropForeign('contract_service_service_id_foreign');
            $table->dropForeign('contract_service_customer_package_id_foreign');
        });

        Schema::table('contract_document_designs', function (Blueprint $table) {
            $table->dropForeign('contract_document_designs_service_id_foreign');
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropForeign('contract_templates_service_id_foreign');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign('leads_applicant_id_foreign');
        });

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
