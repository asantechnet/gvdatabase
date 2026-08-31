<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->decimal('salary', 20, 4)->change();
            $table->foreignId('currency_id')->nullable()->after('salary')->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 10)->nullable()->after('currency_id');
            $table->string('currency_symbol', 10)->nullable()->after('currency_code');
            $table->unsignedTinyInteger('currency_decimal_places')->nullable()->after('currency_symbol');
            $table->decimal('rate', 20, 8)->nullable()->after('currency_decimal_places');
        });

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->string('currency_name')->nullable()->after('currency_code');
            $table->string('currency_symbol', 10)->nullable()->after('currency_name');
            $table->unsignedTinyInteger('currency_decimal_places')->nullable()->after('currency_symbol');
        });

        // Legacy position salaries were entered in the then-current base currency.
        $base = DB::table('currencies')->where('is_base', true)->first();
        if ($base) {
            DB::table('positions')->whereNull('currency_id')->update([
                'currency_id' => $base->id,
                'currency_code' => $base->code,
                'currency_symbol' => $base->symbol,
                'currency_decimal_places' => $base->decimal_places,
                'rate' => 1,
            ]);
            DB::table('worthy_employees')->whereNull('currency_name')->where('currency_id', $base->id)->update([
                'currency_name' => $base->name,
                'currency_symbol' => $base->symbol,
                'currency_decimal_places' => $base->decimal_places,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->dropColumn(['currency_name', 'currency_symbol', 'currency_decimal_places']);
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['currency_code', 'currency_symbol', 'currency_decimal_places', 'rate']);
            $table->integer('salary')->change();
        });
    }
};
