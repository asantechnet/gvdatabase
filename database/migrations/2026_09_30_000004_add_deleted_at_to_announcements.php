<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcement uses the Auditable trait, which composes SoftDeletes. The
 * create migration omitted $table->softDeletes(), so every query against the
 * model failed with "Unknown column 'announcements.deleted_at'" because the
 * SoftDeletes global scope always filters on deleted_at.
 *
 * Announcements stay immutable through the API (there is no update or delete
 * route), so soft deletion is never triggered intentionally here. The column
 * exists purely to satisfy the trait, exactly like every other Auditable
 * model's table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('announcements', 'deleted_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('announcements', 'deleted_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};