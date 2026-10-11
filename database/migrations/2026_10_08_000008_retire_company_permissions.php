<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the permission grants that belonged to the retired counterparty
 * companies surface. Assignment rows cascade with the permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->whereIn('name', [
            'view_companies',
            'create_companies',
            'edit_companies',
            'delete_companies',
        ])->delete();
    }

    public function down(): void
    {
        // Retired endpoints and grants are intentionally not restored.
    }
};
