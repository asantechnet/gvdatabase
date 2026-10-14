<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real-time notification delivery flag.
 *
 * `users.unread_notifications` is a cheap per-user "there is something to
 * fetch" bit. `Notification::deliverTo()` raises it for every delivered
 * recipient and the heartbeat endpoint drains (and clears) it, so the
 * frontend can poll a tiny endpoint instead of the whole inbox.
 *
 * The two indexes back the heartbeat lookups: the pivot index covers
 * "this user's undrained rows" and the notifications index keeps the
 * final `orderByDesc('date_time')` listing cheap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('unread_notifications')->default(false)->after('acc_status');
            $table->index(['unread_notifications'], 'users_unread_notifications_index');
        });

        Schema::table('notification_user', function (Blueprint $table) {
            // Explicit short name: the Laravel-generated one is 66 characters
            // and MySQL rejects identifiers longer than 64.
            $table->index(
                ['user_id', 'is_read', 'deleted_at', 'notification_id'],
                'notification_user_heartbeat_index',
            );
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['date_time', 'id'], 'notifications_date_time_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_date_time_id_index');
        });

        Schema::table('notification_user', function (Blueprint $table) {
            $table->dropIndex('notification_user_heartbeat_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_unread_notifications_index');
            $table->dropColumn('unread_notifications');
        });
    }
};
