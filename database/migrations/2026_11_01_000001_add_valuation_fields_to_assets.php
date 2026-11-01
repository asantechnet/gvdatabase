<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // `price` stays the unit price. Currency is required by the API for
            // new records; the column is nullable only so legacy rows can be
            // backfilled below without a destructive table rebuild.
            $table->foreignId('currency_id')->nullable()->after('price')->constrained('currencies')->restrictOnDelete();
            $table->date('acquisition_date')->nullable()->after('currency_id');
            $table->unsignedSmallInteger('useful_life_years')->nullable()->after('acquisition_date');
            $table->decimal('salvage_value', 14, 2)->default(0)->after('useful_life_years');
        });

        $baseCurrencyId = DB::table('currencies')
            ->where('is_base', true)
            ->whereNull('deleted_at')
            ->value('id');

        // A fresh database has no assets yet, so there is nothing to backfill.
        // Once assets exist, silently leaving them without a currency would
        // make their values unreconcilable.
        if (! $baseCurrencyId && DB::table('assets')->exists()) {
            throw new \RuntimeException('Cannot add asset valuation fields: no active base currency is configured. Seed a base currency and rerun the migration.');
        }

        if ($baseCurrencyId) {
            DB::table('assets')
                ->whereNull('currency_id')
                ->update(['currency_id' => $baseCurrencyId]);
        }

        DB::table('assets')
            ->whereNull('acquisition_date')
            ->update(['acquisition_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['acquisition_date', 'useful_life_years', 'salvage_value']);
        });
    }
};
