<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR section: departments, positions, employees, the pending-transaction
 * inbox, marketers, attendance, the leave ledger, salaries and their payment
 * ledger, assets with allocations, bonus nominations and their salary links.
 *
 * Deferred FKs (targets live in later sections) are added by
 * 0001_15_01_000015_add_cross_section_foreign_keys:
 *   - departments.manager_employee_id      -> employees
 *   - pending_transactions.applicant_id    -> applicants
 *   - salary_payments.expense_id           -> expenses
 *   - worthy_employees.source_task_id      -> tasks
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->text('description')->nullable();
            // FK added in the completion pass (employees live below in the
            // final schema, but historically this column predates them).
            $table->unsignedBigInteger('manager_employee_id')->nullable()->index('departments_manager_employee_id_foreign');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('salary', 20, 4);
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_symbol', 10)->nullable();
            $table->unsignedTinyInteger('currency_decimal_places')->nullable();
            $table->decimal('rate', 20, 8)->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
            $table->string('system_key', 80)->nullable()->unique();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('father_name');
            $table->string('contact');
            $table->string('id_number', 100)->nullable()->unique();
            $table->string('guarantor_id_number', 100)->nullable();
            $table->string('guarantor_phone', 30)->nullable();
            $table->text('guarantor_address')->nullable();
            $table->string('guarantor_licence')->nullable();
            $table->string('education_lvl');
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->string('employment_type')->default('salaried');
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('total_worked_hours', 14, 2)->default(0);
            $table->text('addr');
            $table->string('avatar')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->string('document_mime', 120)->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->string('department')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('join_date');
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->string('contract_duration');
            $table->string('bank_account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('position_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id');
            $table->foreignId('user_id');
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'user_id'], 'position_user_position_id_user_id_unique');
            $table->foreign('position_id', 'position_user_position_id_foreign')->references('id')->on('positions')->cascadeOnDelete();
            $table->foreign('user_id', 'position_user_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('pending_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type');
            $table->text('reason');
            $table->decimal('amount', 20, 6);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            // FK added in the completion pass (applicants live in a later section).
            $table->unsignedBigInteger('applicant_id')->nullable()->index('pending_transactions_applicant_id_foreign');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->nullableMorphs('posted_record');
            $table->index(['approval_status', 'transaction_type']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'paid-leave', 'unpaid-leave']);
            $table->decimal('leave_duration_days', 3, 1)->default(0.0);
            $table->decimal('paid_leave_days', 3, 1)->default(0.0);
            $table->decimal('unpaid_leave_days', 3, 1)->default(0.0);
            $table->time('logged_at');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy_meters')->nullable();
            $table->unsignedInteger('check_in_distance_meters')->nullable();
            $table->string('check_in_source', 30)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy_meters')->nullable();
            $table->unsignedInteger('check_out_distance_meters')->nullable();
            $table->string('check_out_source', 30)->nullable();
            $table->timestamp('geofence_exit_detected_at')->nullable();
            $table->unsignedSmallInteger('break_minutes')->nullable();
            $table->unsignedInteger('gross_minutes')->nullable();
            $table->unsignedInteger('net_worked_minutes')->nullable();
            $table->unsignedInteger('required_minutes')->nullable();
            $table->unsignedInteger('late_arrival_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('missing_punch_state', 20)->default('none');
            $table->boolean('is_overnight_shift')->default(false);
            $table->string('calculation_hash', 64)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'date']);
            $table->index(['employee_id', 'checked_out_at']);
            $table->index(['date', 'office_id', 'status']);
            $table->index(['calculated_at', 'calculation_hash']);
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            // Constraint/index names keep the legacy `paid_time_offs_` prefix.
            $table->foreignId('employee_id');
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->decimal('duration_days', 3, 1)->default(1.0);
            $table->string('reason', 1000);
            $table->text('approval_note')->nullable();
            $table->boolean('is_granted')->default(false);
            $table->boolean('is_paid')->nullable();
            $table->decimal('paid_days', 3, 1)->nullable();
            $table->boolean('paid_override')->default(false);
            $table->foreignId('requested_by')->nullable();
            $table->foreignId('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'date'], 'paid_time_offs_employee_id_date_unique');
            $table->index(['employee_id', 'year'], 'paid_time_offs_employee_id_year_index');
            $table->foreign('employee_id', 'paid_time_offs_employee_id_foreign')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('requested_by', 'paid_time_offs_requested_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('granted_by', 'paid_time_offs_granted_by_foreign')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payslip_number')->nullable()->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('salary_month', 7);
            $table->decimal('amount_paid', 20, 8);
            $table->decimal('worked_days', 5, 1)->default(30.0);
            $table->integer('total_days')->default(30);
            $table->decimal('paid_leave_days', 5, 1)->default(0.0);
            $table->decimal('absent_days', 5, 1)->default(0.0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0.0);
            $table->decimal('bonus', 20, 8)->default(0);
            $table->decimal('total_amount', 20, 8);
            $table->decimal('amount_due', 20, 8)->nullable();
            $table->decimal('amount_paid_total', 20, 8)->default(0);
            $table->string('payment_status', 20)->default('open');
            $table->string('remarks', 255)->nullable();
            $table->date('payment_date');
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 20)->nullable();
            $table->unsignedTinyInteger('currency_decimal_places')->nullable();
            $table->string('currency_snapshot_source', 40)->default('recorded_at_creation');
            $table->text('currency_snapshot_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('cnv_curr_shrt', 10);
            $table->string('base_curr_shrt', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('base_amount', 20, 4);
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->unique(['employee_id', 'salary_month'], 'salaries_employee_month_unique');
            $table->index('recorded_by', 'salaries_recorded_by_index');
        });

        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 20, 8);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->nullable()->unique();
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            // FK added in the completion pass (expenses live in the finance section).
            $table->unsignedBigInteger('expense_id')->nullable()->unique();
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('amount', 20, 8);
            $table->date('payment_date');
            $table->string('remarks', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['salary_id', 'payment_date'], 'salary_payments_salary_id_payment_date_index');
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number');
            $table->string('item');
            $table->integer('quantity');
            $table->decimal('price', 14, 2)->default(0);
            $table->string('model');
            $table->string('condition')->default('good');
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['asset_id', 'employee_id']);
        });

        Schema::create('worthy_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 10)->nullable();
            $table->unsignedTinyInteger('currency_decimal_places')->nullable();
            $table->string('base_currency_code', 10)->nullable();
            $table->decimal('rate', 20, 8)->nullable();
            $table->decimal('base_amount', 20, 4)->nullable();
            $table->string('currency_snapshot_source', 40)->default('recorded_at_creation');
            // FK added in the completion pass (tasks live in the kanban section).
            $table->unsignedBigInteger('source_task_id')->nullable()->index('worthy_employees_source_task_id_foreign');
            $table->boolean('is_automatic')->default(false);
            $table->decimal('performance_score', 3, 1)->default(0.0);
            $table->text('achievement_details');
            $table->decimal('suggested_amount', 20, 8);
            $table->decimal('selected_amount', 20, 8);
            $table->string('selected_type');
            $table->decimal('granted_amount', 20, 4)->nullable();
            $table->boolean('is_granted')->default(false);
            $table->date('bonus_month');
            $table->string('calculation_key', 80)->nullable()->unique();
            $table->decimal('calculation_score', 12, 4)->nullable();
            $table->json('calculation_inputs')->nullable();
            $table->json('rule_snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'source_task_id'], 'worthy_employee_task_unique');
        });

        Schema::create('salary_worthy_employee', function (Blueprint $table) {
            $table->foreignId('salary_id');
            $table->foreignId('worthy_employee_id');
            $table->timestamps();
            $table->decimal('source_amount', 20, 4)->nullable();
            $table->string('source_currency_code', 10)->nullable();
            $table->decimal('source_rate', 20, 8)->nullable();
            $table->string('salary_currency_code', 10)->nullable();
            $table->decimal('salary_rate', 20, 8)->nullable();
            $table->decimal('converted_amount', 20, 4)->nullable();
            $table->string('conversion_formula', 255)->nullable();
            $table->string('conversion_snapshot_source', 40)->nullable();

            $table->primary(['salary_id', 'worthy_employee_id']);
            $table->unique('worthy_employee_id', 'salary_worthy_employee_worthy_employee_id_unique');
            $table->foreign('salary_id', 'salary_worthy_employee_salary_id_foreign')->references('id')->on('salaries')->cascadeOnDelete();
            $table->foreign('worthy_employee_id')->references('id')->on('worthy_employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_worthy_employee');
        Schema::dropIfExists('worthy_employees');
        Schema::dropIfExists('asset_allocations');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('salary_deductions');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('marketers');
        Schema::dropIfExists('pending_transactions');
        Schema::dropIfExists('position_user');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
