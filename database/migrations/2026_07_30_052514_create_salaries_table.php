<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->string('payslip_number')->nullable()->unique();
            // Assuming an existing 'employees' table
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();

            $table->string('salary_month', 7); // Format: 'YYYY-MM'
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->integer('worked_days')->default(30);
            $table->integer('total_days')->default(30);
            $table->decimal('total_amount', 12, 2); // amount_paid + bonus
            $table->string('remarks', 255)->nullable();
            $table->date('payment_date');

            $table->timestamps();
        });

        Schema::create('salary_worthy_employee', function (Blueprint $table) {
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worthy_employee_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['salary_id', 'worthy_employee_id']);
            $table->unique('worthy_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_worthy_employee');
        Schema::dropIfExists('salaries');
    }
};
