<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('class_type');
            $table->string('deptor'); // Customer name snapshot for issued documents.
            $table->string('status')->default('Issued');
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->string('currency_name');
            $table->decimal('currency_rate', 12, 6)->default(1);
            $table->string('currency_symbol');
            $table->string('currency_shortname');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('payment_method')->nullable();
            $table->json('payment_instructions')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::dropIfExists('invoices');
    }
};
