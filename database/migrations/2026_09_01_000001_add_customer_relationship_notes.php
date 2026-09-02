<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'notes')) {
            Schema::table('customers', fn (Blueprint $table) => $table->text('notes')->nullable()->after('marketer'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'notes')) {
            Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('notes'));
        }
    }
};
