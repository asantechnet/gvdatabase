<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance section: the service catalogue, payment instructions, invoices with
 * their per-service lines and commission recipients, incomes, expenses, the
 * per-service commission configuration, commission and outsourcing ledgers,
 * and currency rate history.
 *
 * FK deferred to 0001_15_01_000015_add_cross_section_foreign_keys:
 *   - expenses.project_id -> projects (nullOnDelete)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('category', 40)->default('general');
            $table->text('description')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status');
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category', 'services_category_index');
        });

        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->text('instruction');
            $table->timestamps();
            $table->softDeletes();

            $table->index('method', 'payment_details_method_index');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('deptor');
            $table->string('status')->default('Issued');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0.00);
            $table->string('currency_name');
            $table->string('currency_symbol');
            $table->string('currency_shortname');
            $table->decimal('subtotal', 20, 6)->default(0);
            $table->decimal('amount', 20, 6)->default(0);
            $table->decimal('discount', 20, 6)->default(0);
            $table->text('notes')->nullable();
            // Immutable customer snapshot columns (see warning.md).
            $table->string('customer_company')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_contact')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_tax_number')->nullable();
            $table->foreignId('payment_detail_id')->nullable()->constrained('payment_details')->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->json('payment_instructions')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('cnv_curr_shrt', 10);
            $table->string('base_curr_shrt', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('base_amount', 20, 6);

            $table->index('recorded_by', 'invoices_recorded_by_index');
            $table->index('applicant_id', 'invoices_applicant_id_idx');
        });

        Schema::create('invoice_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('marketer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('outsourcing_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->decimal('snapshot_outsourcing_percentage', 5, 2)->nullable();
            $table->decimal('snapshot_commission_percentage', 5, 2)->nullable();
            $table->decimal('snapshot_marketer_commission_percentage', 5, 2)->nullable();
            $table->string('snapshot_name');
            $table->decimal('snapshot_service_fee', 14, 2);
            $table->string('snapshot_service_currency_code', 3);
            $table->string('snapshot_service_currency_symbol', 10);
            $table->decimal('snapshot_service_rate', 20, 8)->nullable();
            $table->decimal('snapshot_converted_fee', 20, 6);
            $table->integer('quantity')->default(1);
            $table->decimal('line_total', 20, 6);
            $table->timestamps();
            $table->string('snapshot_cnv_curr_shrt', 10);
            $table->string('snapshot_base_curr_shrt', 10);
            $table->decimal('snapshot_rate', 20, 8);
            $table->decimal('snapshot_invoice_rate', 20, 8)->nullable();
            $table->foreignId('contract_service_id')->nullable()->constrained('contract_service')->nullOnDelete();

            $table->unique(['invoice_id', 'service_id'], 'invoice_service_invoice_id_service_id_unique');
            $table->index(['employee_id', 'service_id'], 'invoice_service_employee_id_service_id_index');
            $table->index('marketer_employee_id', 'invoice_service_marketer_employee_idx');
            $table->index('outsourcing_company_id', 'invoice_service_outsourcing_company_idx');
        });

        Schema::create('invoice_service_commission_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_service_id')->constrained('invoice_service')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['invoice_service_id', 'employee_id'], 'invoice_service_commission_recipient_unique');
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->nullable()->unique();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->decimal('amount', 20, 6);
            $table->integer('quantity')->default(1);
            $table->string('currency', 3)->default('USD');
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
            $table->string('cnv_curr_shrt', 10);
            $table->string('base_curr_shrt', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('base_amount', 20, 6);
            $table->decimal('invoice_amount', 20, 6)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained('pending_transactions')->nullOnDelete();

            $table->index('invoice_id', 'incomes_invoice_id_index');
            $table->index('recorded_by', 'incomes_recorded_by_index');
            $table->index('company_id', 'income_company_idx');
            $table->index(['applicant_id', 'date'], 'incomes_applicant_id_date_index');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('salary_id')->nullable()->unique()->constrained('salaries')->cascadeOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->string('document_mime', 120)->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->decimal('amount', 12, 2);
            $table->integer('quantity')->default(1);
            $table->string('currency', 3)->default('USD');
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
            $table->string('cnv_curr_shrt', 10);
            $table->string('base_curr_shrt', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('base_amount', 20, 4);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            // FK added in the completion pass (projects live in the kanban section).
            $table->unsignedBigInteger('project_id')->nullable()->index('expense_project_idx');
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained('pending_transactions')->nullOnDelete();

            $table->index('recorded_by', 'expenses_recorded_by_index');
            $table->index('company_id', 'expense_company_idx');
            $table->index(['applicant_id', 'date'], 'expenses_applicant_id_date_index');
        });

        Schema::create('employee_service_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['employee_id', 'service_id'], 'employee_service_commissions_employee_id_service_id_unique');
        });

        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->nullable()->unique();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_base_amount', 20, 6);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('payment_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('expense_id')->nullable()->unique()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status', 'period_start', 'period_end'], 'commission_settlement_period_idx');
        });

        Schema::create('commission_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->nullable()->constrained('incomes')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('invoice_service_id')->nullable()->constrained('invoice_service')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('commission_settlement_id')->nullable()->constrained('commission_settlements')->nullOnDelete();
            $table->string('entry_type')->default('earned');
            $table->string('commission_role', 20)->default('delivery');
            $table->date('earned_at');
            $table->decimal('source_payment_base_amount', 20, 6);
            $table->decimal('eligible_base_amount', 20, 6);
            $table->decimal('percentage', 5, 2);
            $table->decimal('commission_base_amount', 20, 6);
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('contract_service_id')->nullable()->constrained('contract_service')->nullOnDelete();

            $table->unique(['income_id', 'invoice_service_id', 'employee_id', 'commission_role'], 'commission_accrual_source_employee_role_unique');
            $table->index(['employee_id', 'status', 'earned_at'], 'commission_accruals_employee_id_status_earned_at_index');
            $table->index('income_id', 'commission_accrual_income_fk_idx');
            $table->index('invoice_service_id', 'commission_accrual_invoice_service_fk_idx');
            $table->index(['employee_id', 'commission_role', 'status', 'earned_at'], 'commission_accrual_role_status_idx');
        });

        Schema::create('outsourcing_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_base_amount', 20, 6);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('payment_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('expense_id')->nullable()->unique()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'period_start', 'period_end'], 'outsourcing_settlement_period_idx');
        });

        Schema::create('outsourcing_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->nullable()->constrained('incomes')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('invoice_service_id')->nullable()->constrained('invoice_service')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('contract_service_id')->nullable()->constrained('contract_service')->nullOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('outsourcing_settlement_id')->nullable()->constrained('outsourcing_settlements')->nullOnDelete();
            $table->date('earned_at');
            $table->decimal('source_payment_base_amount', 20, 6);
            $table->decimal('eligible_base_amount', 20, 6);
            $table->decimal('percentage', 5, 2);
            $table->decimal('outsourcing_base_amount', 20, 6);
            $table->string('entry_type')->default('earned');
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['income_id', 'invoice_service_id'], 'outsourcing_accrual_source_unique');
            $table->index(['company_id', 'status', 'earned_at'], 'outsourcing_accrual_company_status_idx');
        });

        Schema::create('currency_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('rate', 20, 8);
            $table->string('reason', 40)->default('rate_updated');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['currency_id', 'created_at'], 'currency_rate_histories_currency_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rate_histories');
        Schema::dropIfExists('outsourcing_accruals');
        Schema::dropIfExists('outsourcing_settlements');
        Schema::dropIfExists('commission_accruals');
        Schema::dropIfExists('commission_settlements');
        Schema::dropIfExists('employee_service_commissions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('invoice_service_commission_recipients');
        Schema::dropIfExists('invoice_service');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('services');
    }
};
