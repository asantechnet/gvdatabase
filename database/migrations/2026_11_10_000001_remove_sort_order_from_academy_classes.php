<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('academy_classes', 'sort_order')) {
            Schema::table('academy_classes', fn (Blueprint $table) => $table->dropColumn('sort_order'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('academy_classes', 'sort_order')) {
            Schema::table('academy_classes', fn (Blueprint $table) => $table->unsignedInteger('sort_order')->default(0));
        }
    }
};
