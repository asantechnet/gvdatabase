<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee salary advances.
 *
 * An advance is money paid to an employee ahead of the payroll period. It is
 * an employee-level ledger (not tied to one salary) so payroll staff can look
 * up everything an employee has received and remove a row when the employee
 * pays the money back. Like salaries, advances are base-currency only; each
 * row carries the currency snapshot and the payroll expense it created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->string('advance_number', 40)->nullable()->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_symbol', 20)->nullable();
            $table->decimal('rate', 20, 8)->default(1);
            $table->decimal('amount', 20, 8);
            $table->decimal('base_amount', 20, 8)->default(0);
            $table->date('advance_date');
            $table->string('remarks', 255)->nullable();
            $table->foreignId('expense_id')->nullable()->unique()->constrained('expenses')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'advance_date'], 'employee_advances_employee_id_advance_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
