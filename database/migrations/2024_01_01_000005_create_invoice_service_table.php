<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('snapshot_name');
            $table->decimal('snapshot_service_fee', 14, 2);
            $table->string('snapshot_service_currency_code', 3);
            $table->string('snapshot_service_currency_symbol', 10);
            $table->decimal('snapshot_service_currency_rate', 14, 6);
            $table->decimal('snapshot_invoice_currency_rate', 14, 6);
            $table->decimal('snapshot_converted_fee', 14, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->unique(['invoice_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_service');
    }
};
