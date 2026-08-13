<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_key', 100)->nullable()->after('type');
            $table->json('data')->nullable()->after('message');
            $table->string('title')->nullable()->change();
            $table->text('message')->nullable()->change();
            $table->index(['notification_key', 'date_time']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notification_key', 'date_time']);
            $table->dropColumn(['notification_key', 'data']);
            $table->string('title')->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
        });
    }
};
