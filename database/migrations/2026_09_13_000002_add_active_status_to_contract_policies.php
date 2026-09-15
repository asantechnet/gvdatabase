<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_policies', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('type');
            $table->index(['type', 'is_active'], 'contract_policies_type_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('contract_policies', function (Blueprint $table) {
            $table->dropIndex('contract_policies_type_active_index');
            $table->dropColumn('is_active');
        });
    }
};
