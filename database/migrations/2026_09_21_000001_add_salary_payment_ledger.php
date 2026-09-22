<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salaries', function (Blueprint $table): void {
            $table->decimal('amount_due', 20, 8)->nullable()->after('total_amount');
            $table->decimal('amount_paid_total', 20, 8)->default(0)->after('amount_due');
            $table->string('payment_status', 20)->default('open')->after('amount_paid_total');
        });

        Schema::create('salary_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number')->nullable()->unique();
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('amount', 20, 8);
            $table->date('payment_date');
            $table->string('remarks', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['salary_id', 'payment_date']);
        });

        // Existing salary rows were previously themselves the single payment.
        // Preserve that financial history as the first installment.
        DB::table('salaries')->orderBy('id')->each(function (object $salary): void {
            $due = (float) ($salary->total_amount ?? 0) - (float) DB::table('salary_deductions')->where('salary_id', $salary->id)->sum('amount');
            $paid = (float) ($salary->amount_paid ?? 0);
            DB::table('salaries')->where('id', $salary->id)->update([
                'amount_due' => max(0, $due),
                'amount_paid_total' => $paid,
                'payment_status' => $paid <= 0 ? 'open' : ($paid + 0.00000001 >= max(0, $due) ? 'paid' : 'partially_paid'),
            ]);
            if ($paid > 0) {
                $expenseId = DB::table('expenses')->where('salary_id', $salary->id)->value('id');
                DB::table('salary_payments')->insert([
                    'salary_id' => $salary->id,
                    'expense_id' => $expenseId,
                    'amount' => $paid,
                    'payment_date' => $salary->payment_date,
                    'remarks' => $salary->remarks,
                    'recorded_by' => $salary->recorded_by,
                    'created_at' => $salary->created_at ?? now(),
                    'updated_at' => $salary->updated_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::table('salaries', function (Blueprint $table): void {
            $table->dropColumn(['amount_due', 'amount_paid_total', 'payment_status']);
        });
    }
};
