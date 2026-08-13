<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->unique()->after('id');
        });

        DB::table('incomes')->select(['id', 'created_at'])->orderBy('id')->each(function (object $income) {
            $year = $income->created_at ? Carbon::parse($income->created_at)->format('Y') : now()->format('Y');

            DB::table('incomes')->where('id', $income->id)->update([
                'receipt_number' => sprintf('RCP-%s-%06d', $year, $income->id),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
