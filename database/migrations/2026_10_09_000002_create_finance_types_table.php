<?php

use App\Models\FinanceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_types', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('value');
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['kind', 'value']);
            $table->unique(['kind', 'name']);
        });

        foreach (FinanceType::DEFAULTS as $kind => $defaults) {
            $existing = DB::table($kind === 'income' ? 'incomes' : 'expenses')->whereNotNull('type')->distinct()->pluck('type')->all();
            foreach (array_unique([...$defaults, ...$existing]) as $value) {
                DB::table('finance_types')->insertOrIgnore([
                    'kind' => $kind, 'value' => $value, 'name' => $value,
                    'is_system' => ($kind === 'income' && in_array($value, ['Service', 'Other'], true)) || ($kind === 'expense' && in_array($value, ['Salaries', 'Pending transaction'], true)),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::table('settings', fn (Blueprint $table) => $table->string('website_address')->nullable()->change());
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_types');
        DB::table('settings')->whereNull('website_address')->update(['website_address' => '']);
        Schema::table('settings', fn (Blueprint $table) => $table->string('website_address')->nullable(false)->change());
    }
};
