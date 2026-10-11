<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the permission grants that belonged to the retired commission and
 * outsourcing ledgers. Assignment rows cascade with the permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->whereIn('name', [
            'view_commissions',
            'manage_commission_settlements',
            'approve_commissions',
            'pay_commissions',
            'view_outsourcing',
            'manage_outsourcing_settlements',
            'approve_outsourcing',
            'pay_outsourcing',
        ])->delete();
    }

    public function down(): void
    {
        // Retired endpoints and grants are intentionally not restored.
    }
};
