<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditing is opt-in. Existing installations receive the same safe default
     * as fresh installs; administrators can enable individual business areas
     * from Audits → Audited Tables.
     */
    public function up(): void
    {
        if (Schema::hasTable('audit_resource_settings')) {
            DB::table('audit_resource_settings')->update(['enabled' => false]);
        }
    }

    public function down(): void
    {
        // Do not re-enable scopes automatically: that would overwrite an
        // administrator's deliberate choices made after this migration.
    }
};
