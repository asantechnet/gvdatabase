<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receipt's value is protected after issue. Changing amount, quantity,
 * currency or rate now requires a written reason, stored beside the row so the
 * audit entry explains why the value moved. No backfill: historical receipts
 * never had a reason and are not rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->string('revaluation_reason', 2000)->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->dropColumn('revaluation_reason');
        });
    }
};
