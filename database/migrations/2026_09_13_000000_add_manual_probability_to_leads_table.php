<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // A percentage entered by a relationship owner is a deliberate
            // assessment and must not be silently replaced by stage defaults.
            $table->boolean('probability_is_manual')->default(false)->after('probability');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('probability_is_manual');
        });
    }
};
