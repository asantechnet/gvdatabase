<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-establish employee_service_commissions as the single source of an
     * employee's delivery capability and rate. The short-lived
     * employee_customer_service_commissions table is retained as historical
     * invoice/audit context, but is not used for future eligibility.
     */
    public function up(): void
    {
        if (! Schema::hasTable('employee_service_commissions')
            || ! Schema::hasTable('employee_customer_service_commissions')) {
            return;
        }

        $now = now();
        $legacyCapabilities = DB::table('employee_customer_service_commissions as commission')
            ->join('customer_service as customer_service', 'customer_service.id', '=', 'commission.customer_service_id')
            ->whereNull('customer_service.deleted_at')
            ->select([
                'commission.employee_id',
                'customer_service.service_id',
                'commission.percentage',
            ])
            ->orderBy('commission.id')
            ->get()
            ->groupBy(fn (object $row) => "{$row->employee_id}:{$row->service_id}");

        // Only infer a catalogue rate where the historical customer-specific
        // records agree. Differing historical rates need an intentional human
        // decision and must never be silently flattened into a new default.
        foreach ($legacyCapabilities as $rows) {
            $first = $rows->first();
            $rates = $rows->map(fn (object $row) => number_format((float) $row->percentage, 2, '.', ''))->unique();

            if ($rates->count() !== 1 || ! $first) {
                continue;
            }

            $alreadyConfigured = DB::table('employee_service_commissions')
                ->where('employee_id', $first->employee_id)
                ->where('service_id', $first->service_id)
                ->exists();

            if (! $alreadyConfigured) {
                DB::table('employee_service_commissions')->insert([
                    'employee_id' => $first->employee_id,
                    'service_id' => $first->service_id,
                    'percentage' => $rates->first(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // An old customer agreement only receives a delivery owner where it
        // had exactly one historic commission employee and that person has a
        // canonical capability after the safe normalization above.
        DB::table('customer_service')
            ->whereNull('employee_id')
            ->whereNull('deleted_at')
            ->select(['id', 'service_id'])
            ->orderBy('id')
            ->each(function (object $customerService): void {
                $employeeIds = DB::table('employee_customer_service_commissions')
                    ->where('customer_service_id', $customerService->id)
                    ->pluck('employee_id')
                    ->unique()
                    ->values();

                if ($employeeIds->count() !== 1) {
                    return;
                }

                $employeeId = $employeeIds->first();
                $hasCapability = DB::table('employee_service_commissions')
                    ->join('employees', 'employees.id', '=', 'employee_service_commissions.employee_id')
                    ->where('employee_id', $employeeId)
                    ->where('service_id', $customerService->service_id)
                    ->where('employees.employment_type', 'commission')
                    ->exists();

                if ($hasCapability) {
                    DB::table('customer_service')
                        ->where('id', $customerService->id)
                        ->whereNull('employee_id')
                        ->update(['employee_id' => $employeeId, 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // This migration only makes conservative, information-preserving
        // backfills. Do not delete established staff capabilities or delivery
        // assignments during rollback.
    }
};
