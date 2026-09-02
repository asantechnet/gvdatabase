<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('visitors', 'email')) {
            Schema::table('visitors', fn (Blueprint $table) => $table->string('email')->nullable()->after('contact'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('visitors', 'email')) {
            Schema::table('visitors', fn (Blueprint $table) => $table->dropColumn('email'));
        }
    }
};
