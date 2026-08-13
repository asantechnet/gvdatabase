<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
        });

        $fallbackCurrencyId = DB::table('currencies')->where('is_base', true)->value('id')
            ?? DB::table('currencies')->orderBy('id')->value('id');

        DB::table('expenses')->select(['id', 'currency'])->orderBy('id')->each(function (object $expense) use ($fallbackCurrencyId) {
            $currencyId = DB::table('currencies')->where('code', $expense->currency)->value('id') ?? $fallbackCurrencyId;
            DB::table('expenses')->where('id', $expense->id)->update(['currency_id' => $currencyId]);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
