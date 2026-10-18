<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real-time audit delivery mirrors the notification heartbeat: a cheap per-user
 * flag is raised when an audit row is written, the heartbeat returns the rows
 * created after the user's cursor and then clears the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('unread_audit_logs')->default(false)->after('unread_notifications');
            $table->timestamp('audit_logs_seen_at')->nullable()->after('unread_audit_logs');
            // An id cursor is exact where a timestamp is not: several audit rows
            // can share a second.
            $table->unsignedBigInteger('audit_logs_seen_id')->nullable()->after('audit_logs_seen_at');
            $table->index(['unread_audit_logs'], 'users_unread_audit_logs_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_unread_audit_logs_index');
            $table->dropColumn(['unread_audit_logs', 'audit_logs_seen_at', 'audit_logs_seen_id']);
        });
    }
};
