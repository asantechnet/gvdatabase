<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'connected_accounts')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('connected_accounts');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'connected_accounts')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('connected_accounts')->nullable()->after('account_settings');
            });
        }
    }
};
