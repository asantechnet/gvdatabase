<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_interactions') || Schema::hasColumn('crm_interactions', 'applicant_id')) {
            return;
        }

        Schema::table('crm_interactions', function (Blueprint $table) {
            $table->foreignId('applicant_id')->nullable()->after('contacted_at')
                ->constrained('applicants')->nullOnDelete();
            $table->index(['applicant_id', 'contactable_type', 'contactable_id'], 'crm_interactions_applicant_scope_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_interactions') || ! Schema::hasColumn('crm_interactions', 'applicant_id')) {
            return;
        }

        Schema::table('crm_interactions', function (Blueprint $table) {
            $table->dropIndex('crm_interactions_applicant_scope_index');
            $table->dropConstrainedForeignId('applicant_id');
        });
    }
};
