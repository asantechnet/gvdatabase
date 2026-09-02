<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_interactions') && ! Schema::hasColumn('crm_interactions', 'deleted_at')) {
            Schema::table('crm_interactions', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_interactions', 'deleted_at')) {
            Schema::table('crm_interactions', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
