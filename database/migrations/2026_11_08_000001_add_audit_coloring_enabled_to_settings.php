<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit colouring is an optional presentation aid. It stays off until an
 * administrator enables it in Settings → Operations; the audit history itself
 * is never affected by this flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('audit_coloring_enabled')->default(false)->after('missing_attendance_status');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('audit_coloring_enabled');
        });
    }
};
