<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('dark_primary_color')->nullable()->after('secondary_color');
            $table->string('dark_secondary_color')->nullable()->after('dark_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['dark_primary_color', 'dark_secondary_color']);
        });
    }
};
