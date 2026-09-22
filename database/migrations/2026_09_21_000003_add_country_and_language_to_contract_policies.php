<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_policies', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->index(['type', 'country_id', 'language_id', 'is_active'], 'contract_policy_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('contract_policies', function (Blueprint $table): void {
            $table->dropIndex('contract_policy_scope_index');
            $table->dropConstrainedForeignId('language_id');
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
