<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Each contract holds exactly one service.
 *
 * New/updated contracts are limited to a single `contract_service` line by
 * API validation (see ContractController) and package issuance creates one
 * contract per packaged service (see PackageIssuanceService).
 *
 * This migration only heals historical data: contracts left with more than
 * one line keep their earliest line; surplus lines are soft-deleted so no
 * invoice/accrual history loses its source row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $contractIds = DB::table('contract_service')
            ->select('contract_id', DB::raw('COUNT(*) as line_count'))
            ->groupBy('contract_id')
            ->having('line_count', '>', 1)
            ->pluck('contract_id');

        foreach ($contractIds as $contractId) {
            $keep = DB::table('contract_service')
                ->where('contract_id', $contractId)
                ->orderBy('id')
                ->value('id');

            DB::table('contract_service')
                ->where('contract_id', $contractId)
                ->where('id', '!==', $keep)
                ->update(['deleted_at' => now()]);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: restoring surplus lines would re-violate
        // the single-service rule and could resurrect legitimately deleted
        // rows. The soft-deleted rows remain recoverable by id if needed.
    }
};
