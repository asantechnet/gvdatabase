<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employees gain an explicit active/inactive state. Soft deletion stays a
 * separate, destructive concept: `inactive` keeps the profile and all history
 * intact while removing the person from every employee directory GET response.
 * Existing rows inherit the `active` default from the column definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('status', 20)->default('active');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
