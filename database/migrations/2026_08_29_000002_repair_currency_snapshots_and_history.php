<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('rate', 20, 8);
            $table->string('reason', 40)->default('rate_updated');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['currency_id', 'created_at']);
        });

        foreach (['invoices', 'incomes', 'expenses', 'salaries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('base_currency_id')->nullable()->after('currency_id')->constrained('currencies')->nullOnDelete();
            });
        }

        Schema::table('incomes', function (Blueprint $table) {
            $table->decimal('invoice_amount', 20, 4)->nullable()->after('base_amount');
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->decimal('snapshot_service_rate', 20, 8)->nullable()->after('snapshot_service_currency_symbol');
            $table->decimal('snapshot_invoice_rate', 20, 8)->nullable()->after('snapshot_rate');
        });

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('employee_id')->constrained('currencies')->nullOnDelete();
            $table->foreignId('base_currency_id')->nullable()->after('currency_id')->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 10)->nullable()->after('base_currency_id');
            $table->string('base_currency_code', 10)->nullable()->after('currency_code');
            $table->decimal('rate', 20, 8)->nullable()->after('base_currency_code');
            $table->decimal('base_amount', 20, 4)->nullable()->after('rate');
        });

        Schema::table('salary_worthy_employee', function (Blueprint $table) {
            $table->string('source_currency_code', 10)->nullable()->after('source_amount');
            $table->decimal('source_rate', 20, 8)->nullable()->after('source_currency_code');
            $table->string('salary_currency_code', 10)->nullable()->after('source_rate');
            $table->decimal('salary_rate', 20, 8)->nullable()->after('salary_currency_code');
            $table->decimal('converted_amount', 20, 4)->nullable()->after('salary_rate');
            $table->string('conversion_formula', 255)->nullable()->after('converted_amount');
            $table->string('conversion_snapshot_source', 40)->nullable()->after('conversion_formula');
        });

        // Preserve the legacy base-currency identity where the stored code is
        // still known.  Historic booked totals are intentionally not recalculated:
        // a row without its original source rate has no safe conversion path.
        $currencies = DB::table('currencies')->select('id', 'code', 'rate')->get();
        foreach (['invoices', 'incomes', 'expenses', 'salaries'] as $tableName) {
            foreach ($currencies as $currency) {
                DB::table($tableName)->whereNull('base_currency_id')
                    ->where('base_curr_shrt', $currency->code)
                    ->update(['base_currency_id' => $currency->id]);
            }
        }

        DB::table('incomes')->whereNull('invoice_amount')->whereNotNull('invoice_id')
            ->update(['invoice_amount' => DB::raw('amount * COALESCE(quantity, 1)')]);
        DB::table('invoice_service')->whereNull('snapshot_invoice_rate')
            ->update(['snapshot_invoice_rate' => DB::raw('snapshot_rate')]);

        $now = now();
        $currentBaseId = DB::table('currencies')->where('is_base', true)->value('id');
        foreach ($currencies as $currency) {
            DB::table('currency_rate_histories')->insert([
                'currency_id' => $currency->id, 'base_currency_id' => $currentBaseId,
                'rate' => $currency->rate, 'reason' => 'migration_baseline', 'recorded_by' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('salary_worthy_employee', function (Blueprint $table) {
            $table->dropColumn(['source_currency_code', 'source_rate', 'salary_currency_code', 'salary_rate', 'converted_amount', 'conversion_formula', 'conversion_snapshot_source']);
        });

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropConstrainedForeignId('base_currency_id');
            $table->dropColumn(['currency_code', 'base_currency_code', 'rate', 'base_amount']);
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->dropColumn(['snapshot_service_rate', 'snapshot_invoice_rate']);
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn('invoice_amount');
        });

        foreach (['invoices', 'incomes', 'expenses', 'salaries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('base_currency_id');
            });
        }

        Schema::dropIfExists('currency_rate_histories');
    }
};
