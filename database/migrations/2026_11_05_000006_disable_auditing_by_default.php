<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_resource_settings')) {
            return;
        }

        DB::table('audit_resource_settings')->update([
            'enabled' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The previous enabled/disabled mix is deliberately not restored.
        // Auditing is opt-in from this release onward.
    }
};
