<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->json('roles')->nullable()->after('address');
            $table->string('acc_status')->default('active')->after('roles');
            $table->json('account_settings')->nullable()->after('acc_status');
            $table->json('connected_accounts')->nullable()->after('account_settings');
            $table->text('two_factor_secret')->nullable()->after('connected_accounts');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'roles', 'acc_status', 'account_settings', 'connected_accounts', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
