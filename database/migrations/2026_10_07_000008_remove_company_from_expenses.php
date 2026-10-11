<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'company_id')) {
            return;
        }

        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropIndex('expense_company_idx');
                $table->dropColumn('company_id');
            });
        });
    }

    public function down(): void
    {
        // Retired company links are intentionally not restored.
    }
};
