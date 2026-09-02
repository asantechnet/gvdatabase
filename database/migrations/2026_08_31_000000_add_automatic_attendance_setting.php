<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Default to the established behaviour so existing installations do
            // not unexpectedly stop recording attendance after upgrading.
            $table->boolean('automatic_attendance_enabled')->default(true)->after('default_break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('automatic_attendance_enabled');
        });
    }
};
