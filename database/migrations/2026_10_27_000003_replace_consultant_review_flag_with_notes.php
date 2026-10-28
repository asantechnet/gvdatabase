<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['visitors', 'applicants'] as $table) {
            if (! Schema::hasColumn($table, 'consultant_review')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->text('consultant_review')->nullable());
            }
        }

        if (Schema::hasColumn('visitors', 'reviewed_by_consultant')) {
            // Preserve the old sign-off as a useful starting note instead of
            // silently throwing away a completed review.
            DB::table('visitors')->where('reviewed_by_consultant', true)
                ->whereNull('consultant_review')
                ->update(['consultant_review' => 'Reviewed']);
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropIndex('visitors_reviewed_by_consultant_index');
                $table->dropColumn('reviewed_by_consultant');
            });
        }

        $permissionId = DB::table('permissions')->where('name', 'review_visitors')->whereNull('deleted_at')->value('id');
        if ($permissionId) {
            foreach (DB::table('roles')->whereIn('name', ['counselor', 'senior_counselor'])->pluck('id') as $roleId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visitors', 'reviewed_by_consultant')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->boolean('reviewed_by_consultant')->default(false);
                $table->index('reviewed_by_consultant', 'visitors_reviewed_by_consultant_index');
            });
        }
        DB::table('visitors')->whereNotNull('consultant_review')->where('consultant_review', '!=', '')
            ->update(['reviewed_by_consultant' => true]);

        foreach (['visitors', 'applicants'] as $table) {
            if (Schema::hasColumn($table, 'consultant_review')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('consultant_review'));
            }
        }
    }
};
