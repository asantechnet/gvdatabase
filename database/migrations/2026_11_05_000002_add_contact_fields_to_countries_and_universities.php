<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional contact details for the two reference catalogues that appear on a
 * visa case. `portal_url` is free text on purpose: the university or embassy
 * portal address is stored exactly as the operator typed it and normalized
 * into a link only where it is rendered.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['countries', 'universities'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('phone', 50)->nullable()->after('name');
                $table->string('email', 255)->nullable()->after('phone');
                $table->text('portal_url')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        foreach (['countries', 'universities'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['phone', 'email', 'portal_url']);
            });
        }
    }
};
