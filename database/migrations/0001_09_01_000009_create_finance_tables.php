<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance section: incomes, expenses and currency rate history.
 */
return new class extends Migration
{
    public function up(): void
    {

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->nullable()->unique();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
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
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained('pending_transactions')->nullOnDelete();

            $table->index('recorded_by', 'incomes_recorded_by_index');
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
            $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained('pending_transactions')->nullOnDelete();

            $table->index('recorded_by', 'expenses_recorded_by_index');
            $table->index(['applicant_id', 'date'], 'expenses_applicant_id_date_index');
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
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
    }
};
