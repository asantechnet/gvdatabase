<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('duration');
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('method')->index();
            $table->text('instruction');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('class_type');
            $table->string('deptor'); // Customer name snapshot for issued documents.
            $table->string('status')->default('Issued');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->string('currency_name');
            $table->string('currency_symbol');
            $table->string('currency_shortname');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('customer_company')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_contact')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_tax_number')->nullable();
            $table->foreignId('payment_detail_id')
                ->nullable()
                ->constrained('payment_details')
                ->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->json('payment_instructions')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('cnv_curr_shrt', 10);
            $table->string('base_curr_shrt', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('base_amount', 20, 4);
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->index();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->text('description');
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

            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salary_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->text('description');
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
        });

        Schema::create('invoice_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('snapshot_name');
            $table->decimal('snapshot_service_fee', 14, 2);
            $table->string('snapshot_service_currency_code', 3);
            $table->string('snapshot_service_currency_symbol', 10);
            $table->decimal('snapshot_converted_fee', 14, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->string('snapshot_cnv_curr_shrt', 10);
            $table->string('snapshot_base_curr_shrt', 10);
            $table->decimal('snapshot_rate', 20, 8);

            $table->unique(['invoice_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_service');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('services');
    }
};
