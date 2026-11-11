<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `started_at` was declared with ON UPDATE CURRENT_TIMESTAMP, so the update
     * that finishes a run refreshed the start time from the database clock
     * (server-local, not UTC). Recorded start times drifted ahead of
     * `finished_at` and stale-run detection used the wrong moment.
     */
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->timestamp('started_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->timestamp('started_at')->useCurrent()->useCurrentOnUpdate()->change();
        });
    }
};
