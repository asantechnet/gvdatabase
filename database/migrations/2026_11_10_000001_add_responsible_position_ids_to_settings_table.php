<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Null means the historical admission/admission_officer defaults.
            // An explicit empty array means no employee is eligible.
            $table->json('responsible_position_ids')->nullable()->after('academy_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('responsible_position_ids');
        });
    }
};
