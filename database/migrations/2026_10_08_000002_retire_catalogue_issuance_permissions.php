<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->whereIn('name', [
            'issue_packages', 'view_educational_services', 'manage_educational_services',
        ])->delete();
    }

    public function down(): void
    {
        // Retired endpoints and grants are intentionally not restored.
    }
};
