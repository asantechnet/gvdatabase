<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the commission ledger on the post-merge architecture.
 *
 * The former ledger (dropped in 2026_10_08_000003) keyed its accruals on
 * invoices and a `services` table that no longer exist. This rebuild keeps the
 * parts that still fit the firm — employee per-service rates, accruals, and
 * the settlement lifecycle — but re-points an accrual at the contract and its
 * `applicant_contract` line, and stores the service as the built-in service
 * string instead of a foreign key.
 *
 * Every step is guarded so the migration is safe on a database that never had
 * the old tables as well as one migrated forward from an older install.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'is_commission_based')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->boolean('is_commission_based')->default(false)->after('salary');
            });
        }

        if (! Schema::hasTable('employee_service_commissions')) {
            Schema::create('employee_service_commissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('service');
                $table->decimal('percentage', 5, 2);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['employee_id', 'service'], 'employee_service_commissions_employee_service_unique');
            });
        }

        if (! Schema::hasTable('commission_settlements')) {
            Schema::create('commission_settlements', function (Blueprint $table): void {
                $table->id();
                $table->string('settlement_number')->nullable()->unique();
                $table->foreignId('employee_id')->constrained('employees');
                $table->foreignId('base_currency_id')->constrained('currencies');
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
        }

        if (! Schema::hasTable('commission_accruals')) {
            Schema::create('commission_accruals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
                $table->foreignId('applicant_contract_id')->nullable()->constrained('applicant_contract')->nullOnDelete();
                $table->foreignId('employee_id')->constrained('employees');
                $table->string('service')->nullable();
                $table->foreignId('base_currency_id')->constrained('currencies');
                $table->foreignId('commission_settlement_id')->nullable()->constrained('commission_settlements')->nullOnDelete();
                $table->string('entry_type')->default('earned');
                $table->date('earned_at');
                $table->decimal('source_base_amount', 20, 6)->default(0);
                $table->decimal('eligible_base_amount', 20, 6)->default(0);
                $table->decimal('percentage', 5, 2)->default(0);
                $table->decimal('commission_base_amount', 20, 6)->default(0);
                $table->text('adjustment_reason')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(
                    ['contract_id', 'applicant_contract_id', 'employee_id'],
                    'commission_accrual_contract_line_employee_unique',
                );
                $table->index(['employee_id', 'status', 'earned_at'], 'commission_accruals_employee_status_earned_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_accruals');
        Schema::dropIfExists('commission_settlements');
        Schema::dropIfExists('employee_service_commissions');

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'is_commission_based')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn('is_commission_based');
            });
        }
    }
};
