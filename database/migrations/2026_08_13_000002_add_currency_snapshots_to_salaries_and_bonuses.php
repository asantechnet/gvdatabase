<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->decimal('amount_paid', 20, 8)->change();
            $table->decimal('bonus', 20, 8)->default(0)->change();
            $table->decimal('total_amount', 20, 8)->change();
            $table->string('currency_code', 10)->nullable()->after('currency_id');
            $table->string('currency_name')->nullable()->after('currency_code');
            $table->string('currency_symbol', 20)->nullable()->after('currency_name');
            $table->unsignedTinyInteger('currency_decimal_places')->nullable()->after('currency_symbol');
            $table->decimal('exchange_rate_to_base', 20, 8)->nullable()->after('currency_decimal_places');
            $table->decimal('amount_paid_base', 20, 4)->nullable()->after('exchange_rate_to_base');
            $table->decimal('bonus_base', 20, 4)->nullable()->after('amount_paid_base');
            $table->decimal('total_amount_base', 20, 4)->nullable()->after('bonus_base');
            $table->string('currency_snapshot_source', 40)->default('recorded_at_creation')->after('total_amount_base');
            $table->text('currency_snapshot_note')->nullable()->after('currency_snapshot_source');
        });

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->decimal('suggested_amount', 20, 8)->change();
            $table->decimal('selected_amount', 20, 8)->change();
            $table->foreignId('currency_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
            $table->string('currency_code', 10)->nullable()->after('currency_id');
            $table->string('currency_name')->nullable()->after('currency_code');
            $table->string('currency_symbol', 20)->nullable()->after('currency_name');
            $table->unsignedTinyInteger('currency_decimal_places')->nullable()->after('currency_symbol');
            $table->decimal('suggested_rate_to_base', 20, 8)->nullable()->after('suggested_amount');
            $table->decimal('suggested_base_amount', 20, 4)->nullable()->after('suggested_rate_to_base');
            $table->foreignId('selected_currency_id')->nullable()->after('selected_amount')->constrained('currencies')->restrictOnDelete();
            $table->string('selected_currency_code', 10)->nullable()->after('selected_currency_id');
            $table->string('selected_currency_name')->nullable()->after('selected_currency_code');
            $table->string('selected_currency_symbol', 20)->nullable()->after('selected_currency_name');
            $table->unsignedTinyInteger('selected_currency_decimal_places')->nullable()->after('selected_currency_symbol');
            $table->decimal('selected_rate_to_base', 20, 8)->nullable()->after('selected_currency_decimal_places');
            $table->decimal('selected_base_amount', 20, 4)->nullable()->after('selected_rate_to_base');
            $table->decimal('granted_amount', 20, 4)->nullable()->after('is_granted');
            $table->foreignId('granted_currency_id')->nullable()->after('granted_amount')->constrained('currencies')->restrictOnDelete();
            $table->string('granted_currency_code', 10)->nullable()->after('granted_currency_id');
            $table->string('granted_currency_name')->nullable()->after('granted_currency_code');
            $table->string('granted_currency_symbol', 20)->nullable()->after('granted_currency_name');
            $table->unsignedTinyInteger('granted_currency_decimal_places')->nullable()->after('granted_currency_symbol');
            $table->decimal('granted_rate_to_base', 20, 8)->nullable()->after('granted_currency_decimal_places');
            $table->decimal('granted_base_amount', 20, 4)->nullable()->after('granted_rate_to_base');
            $table->string('currency_snapshot_source', 40)->default('recorded_at_creation')->after('granted_base_amount');
            $table->text('currency_snapshot_note')->nullable()->after('currency_snapshot_source');
        });

        Schema::table('salary_worthy_employee', function (Blueprint $table) {
            $table->decimal('source_amount', 20, 4)->nullable();
            $table->string('source_currency_code', 10)->nullable();
            $table->decimal('source_rate_to_base', 20, 8)->nullable();
            $table->decimal('base_amount', 20, 4)->nullable();
            $table->string('salary_currency_code', 10)->nullable();
            $table->decimal('salary_rate_to_base', 20, 8)->nullable();
            $table->decimal('converted_amount', 20, 4)->nullable();
            $table->string('conversion_formula', 100)->nullable();
            $table->string('conversion_snapshot_source', 40)->default('recorded_at_inclusion');
        });

        Schema::table('salary_deductions', function (Blueprint $table) {
            $table->decimal('amount', 20, 8)->change();
        });

        $currencies = DB::table('currencies')->get()->keyBy('id');
        $baseCurrency = $currencies->firstWhere('is_base', true) ?? $currencies->first();

        DB::table('salaries')->orderBy('id')->chunkById(100, function ($salaries) use ($currencies): void {
            foreach ($salaries as $salary) {
                $currency = $currencies->get($salary->currency_id);
                $rate = max((float) ($currency?->rate_against_base ?? 1), 0.00000001);
                DB::table('salaries')->where('id', $salary->id)->update([
                    'currency_code' => $currency?->code ?? 'UNK',
                    'currency_name' => $currency?->name ?? 'Unknown legacy currency',
                    'currency_symbol' => $currency?->symbol ?? '',
                    'currency_decimal_places' => $currency?->decimal_places ?? 2,
                    'exchange_rate_to_base' => $rate,
                    'amount_paid_base' => round((float) $salary->amount_paid * $rate, 4),
                    'bonus_base' => round((float) $salary->bonus * $rate, 4),
                    'total_amount_base' => round((float) $salary->total_amount * $rate, 4),
                    'currency_snapshot_source' => 'inferred_current_currency',
                    'currency_snapshot_note' => 'Backfilled from the linked currency current at migration; no historical exchange-rate snapshot was available.',
                ]);
            }
        });

        if ($baseCurrency) {
            $rate = max((float) $baseCurrency->rate_against_base, 0.00000001);
            DB::table('worthy_employees')->orderBy('id')->chunkById(100, function ($bonuses) use ($baseCurrency, $rate): void {
                foreach ($bonuses as $bonus) {
                    $snapshot = [
                        'currency_id' => $baseCurrency->id,
                        'currency_code' => $baseCurrency->code,
                        'currency_name' => $baseCurrency->name,
                        'currency_symbol' => $baseCurrency->symbol,
                        'currency_decimal_places' => $baseCurrency->decimal_places,
                        'suggested_rate_to_base' => $rate,
                        'suggested_base_amount' => round((float) $bonus->suggested_amount * $rate, 4),
                        'selected_currency_id' => $baseCurrency->id,
                        'selected_currency_code' => $baseCurrency->code,
                        'selected_currency_name' => $baseCurrency->name,
                        'selected_currency_symbol' => $baseCurrency->symbol,
                        'selected_currency_decimal_places' => $baseCurrency->decimal_places,
                        'selected_rate_to_base' => $rate,
                        'selected_base_amount' => round((float) $bonus->selected_amount * $rate, 4),
                        'currency_snapshot_source' => 'inferred_base_currency',
                        'currency_snapshot_note' => 'Legacy bonus rows had no currency. The base currency at migration was assumed; verify before financial reporting.',
                    ];
                    if ($bonus->is_granted) {
                        $snapshot += [
                            'granted_amount' => $bonus->selected_amount,
                            'granted_currency_id' => $baseCurrency->id,
                            'granted_currency_code' => $baseCurrency->code,
                            'granted_currency_name' => $baseCurrency->name,
                            'granted_currency_symbol' => $baseCurrency->symbol,
                            'granted_currency_decimal_places' => $baseCurrency->decimal_places,
                            'granted_rate_to_base' => $rate,
                            'granted_base_amount' => round((float) $bonus->selected_amount * $rate, 4),
                        ];
                    }
                    DB::table('worthy_employees')->where('id', $bonus->id)->update($snapshot);
                }
            });
        }

        DB::table('salary_worthy_employee')->orderBy('salary_id')->get()->each(function ($pivot): void {
            $salary = DB::table('salaries')->where('id', $pivot->salary_id)->first();
            $bonus = DB::table('worthy_employees')->where('id', $pivot->worthy_employee_id)->first();
            if (! $salary || ! $bonus) return;
            $sourceRate = max((float) ($bonus->granted_rate_to_base ?? $bonus->selected_rate_to_base ?? 1), 0.00000001);
            $targetRate = max((float) ($salary->exchange_rate_to_base ?? 1), 0.00000001);
            $sourceAmount = (float) ($bonus->granted_amount ?? $bonus->selected_amount);
            $baseAmount = $sourceAmount * $sourceRate;
            DB::table('salary_worthy_employee')
                ->where('salary_id', $pivot->salary_id)
                ->where('worthy_employee_id', $pivot->worthy_employee_id)
                ->update([
                    'source_amount' => $sourceAmount,
                    'source_currency_code' => $bonus->granted_currency_code ?? $bonus->selected_currency_code,
                    'source_rate_to_base' => $sourceRate,
                    'base_amount' => round($baseAmount, 4),
                    'salary_currency_code' => $salary->currency_code,
                    'salary_rate_to_base' => $targetRate,
                    'converted_amount' => round($baseAmount / $targetRate, 4),
                    'conversion_formula' => '(source_amount × source_rate_to_base) ÷ salary_rate_to_base',
                    'conversion_snapshot_source' => 'inferred_current_rates',
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('salary_deductions', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
        });
        Schema::table('salary_worthy_employee', function (Blueprint $table) {
            $table->dropColumn(['source_amount', 'source_currency_code', 'source_rate_to_base', 'base_amount', 'salary_currency_code', 'salary_rate_to_base', 'converted_amount', 'conversion_formula', 'conversion_snapshot_source']);
        });
        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropConstrainedForeignId('selected_currency_id');
            $table->dropConstrainedForeignId('granted_currency_id');
            $table->dropColumn(['currency_code', 'currency_name', 'currency_symbol', 'currency_decimal_places', 'suggested_rate_to_base', 'suggested_base_amount', 'selected_currency_code', 'selected_currency_name', 'selected_currency_symbol', 'selected_currency_decimal_places', 'selected_rate_to_base', 'selected_base_amount', 'granted_amount', 'granted_currency_code', 'granted_currency_name', 'granted_currency_symbol', 'granted_currency_decimal_places', 'granted_rate_to_base', 'granted_base_amount', 'currency_snapshot_source', 'currency_snapshot_note']);
            $table->decimal('suggested_amount', 10, 2)->change();
            $table->decimal('selected_amount', 10, 2)->change();
        });
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'currency_name', 'currency_symbol', 'currency_decimal_places', 'exchange_rate_to_base', 'amount_paid_base', 'bonus_base', 'total_amount_base', 'currency_snapshot_source', 'currency_snapshot_note']);
            $table->decimal('amount_paid', 12, 2)->change();
            $table->decimal('bonus', 12, 2)->default(0)->change();
            $table->decimal('total_amount', 12, 2)->change();
        });
    }
};
