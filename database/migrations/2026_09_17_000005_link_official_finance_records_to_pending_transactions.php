<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['incomes', 'expenses', 'salaries'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('pending_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['incomes', 'expenses', 'salaries'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('pending_transaction_id'));
        }
    }
};
