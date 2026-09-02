<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Currency configuration permits up to six fractional digits. Keep stored
     * receipt values at least that precise so a valid currency amount is never
     * silently truncated before its total is calculated.
     */
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->decimal('amount', 20, 6)->change();
            $table->decimal('base_amount', 20, 6)->change();
            $table->decimal('invoice_amount', 20, 6)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 20, 6)->default(0)->change();
            $table->decimal('amount', 20, 6)->default(0)->change();
            $table->decimal('discount', 20, 6)->default(0)->change();
            $table->decimal('base_amount', 20, 6)->change();
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->decimal('snapshot_converted_fee', 20, 6)->change();
            $table->decimal('line_total', 20, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_service', function (Blueprint $table) {
            $table->decimal('snapshot_converted_fee', 14, 2)->change();
            $table->decimal('line_total', 14, 2)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 14, 2)->default(0)->change();
            $table->decimal('amount', 14, 2)->default(0)->change();
            $table->decimal('discount', 12, 2)->default(0)->change();
            $table->decimal('base_amount', 20, 4)->change();
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->decimal('base_amount', 20, 4)->change();
            $table->decimal('invoice_amount', 20, 4)->nullable()->change();
        });
    }
};
