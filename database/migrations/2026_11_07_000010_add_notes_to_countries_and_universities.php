<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['countries', 'universities'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'notes')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('notes')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['countries', 'universities'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'notes')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('notes');
                });
            }
        }
    }
};
