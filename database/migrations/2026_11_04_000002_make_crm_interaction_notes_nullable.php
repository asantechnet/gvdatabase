<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_interactions', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('crm_interactions', function (Blueprint $table) {
            $table->text('notes')->nullable(false)->change();
        });
    }
};
