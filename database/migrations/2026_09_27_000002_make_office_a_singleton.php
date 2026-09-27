<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the oldest office as the company office. Every office-linked
        // record is retained and reassigned before redundant offices go away.
        $office = DB::table('offices')->orderBy('id')->first();

        if ($office) {
            foreach (['employees', 'attendances', 'assets'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'office_id')) {
                    DB::table($table)->where('office_id', '!=', $office->id)->update(['office_id' => $office->id]);
                }
            }

            DB::table('offices')->where('id', $office->id)->update(['deleted_at' => null]);
            DB::table('offices')->where('id', '!=', $office->id)->delete();
        }

        Schema::table('offices', function (Blueprint $table) {
            // A unique, always-true marker makes the singleton rule hold even
            // outside the HTTP API (imports, seeders, and concurrent writes).
            $table->boolean('is_singleton')->default(true)->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropUnique(['is_singleton']);
            $table->dropColumn('is_singleton');
        });
    }
};
