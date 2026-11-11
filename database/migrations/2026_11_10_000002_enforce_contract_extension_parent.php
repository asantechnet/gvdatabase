<?php

use App\Models\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Report legacy rows that cannot satisfy the extension-parent invariant without
 * manually choosing a parent. The migration deliberately never repairs a row:
 * a guessed parent would attach a live agreement to the wrong origin. New
 * writes are guarded by Contract's model hooks and the contract API.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('contracts')
            ->select(['id', 'contract_number', 'contract_role', 'parent_contract_id', 'status', 'deleted_at'])
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('contract_role', Contract::ROLE_EXTENSION)
                        ->whereNull('parent_contract_id');
                })->orWhere(function ($query): void {
                    $query->where(function ($query): void {
                        $query->whereNull('contract_role')
                            ->orWhere('contract_role', '!=', Contract::ROLE_EXTENSION);
                    })->whereNotNull('parent_contract_id');
                });
            })
            ->orderBy('id')
            ->get();

        foreach ($orphans as $orphan) {
            Log::warning('contract_extension_invariant_orphan', [
                'contract_id' => $orphan->id,
                'contract_number' => $orphan->contract_number,
                'contract_role' => $orphan->contract_role,
                'parent_contract_id' => $orphan->parent_contract_id,
                'status' => $orphan->status,
                'deleted' => $orphan->deleted_at !== null,
            ]);
        }
    }

    public function down(): void
    {
        // No rows are changed, so there is nothing to restore.
    }
};
