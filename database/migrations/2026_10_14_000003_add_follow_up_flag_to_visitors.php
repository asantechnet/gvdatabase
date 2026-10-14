<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM follow-up marking.
 *
 * `visitors.follow_up` marks a visitor as worthy of follow-up. It splits the CRM
 * visitor surface into two lists that share one page: the Visitors list shows the
 * unmarked records, the Follow-up list shows the marked ones. This is separate
 * from `crm_interactions.follow_up_at`, which schedules one conversation.
 *
 * The index backs the list filter, which is now the primary way the visitor page
 * is queried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->boolean('follow_up')->default(false)->after('converted_at');
            $table->index(['follow_up'], 'visitors_follow_up_index');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex('visitors_follow_up_index');
            $table->dropColumn('follow_up');
        });
    }
};
