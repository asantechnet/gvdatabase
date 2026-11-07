<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment requests are retired. An income or expense row is now either a draft
 * awaiting approval or an approved ledger record; a rejected draft keeps its
 * rejection reason for the audit trail. The payment-request table and every
 * link column it owned are removed from the schema.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const FINANCE_TABLES = ['incomes', 'expenses'];

    public function up(): void
    {
        foreach (self::FINANCE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('status', 20)->default('approved')->index();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
            });
        }

        Schema::table('incomes', fn (Blueprint $table) => $table->dropConstrainedForeignId('pending_transaction_id'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('pending_transaction_id'));
        Schema::table('salaries', fn (Blueprint $table) => $table->dropConstrainedForeignId('pending_transaction_id'));
        Schema::table('salary_payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('pending_transaction_id'));

        Schema::dropIfExists('pending_transactions');
    }

    public function down(): void
    {
        Schema::create('pending_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_type');
            $table->text('reason');
            $table->decimal('amount', 20, 6);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('submitted');
            $table->text('rejection_reason')->nullable();
            $table->nullableMorphs('posted_record');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['incomes', 'expenses', 'salaries', 'salary_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('pending_transaction_id')->nullable()->constrained('pending_transactions')->nullOnDelete());
        }

        foreach (self::FINANCE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['status', 'approved_by', 'approved_at', 'rejected_at', 'rejection_reason']);
            });
        }
    }
};
