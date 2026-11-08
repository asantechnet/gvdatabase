<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Contract delivery tasks used to copy the service fee, payment state
        // and start date into the task description. The contract remains the
        // source of truth, and that financial detail must not appear on staff
        // task cards. Match only the generated prefix so hand-written
        // descriptions on contract-linked tasks are preserved.
        DB::table('tasks')
            ->whereNotNull('applicant_contract_id')
            ->where(function ($query): void {
                $query->where('description', 'like', 'Contract %· Fee:%')
                    ->orWhere('description', 'like', 'Contract %· Service fee:%');
            })
            ->update(['description' => null]);
    }

    public function down(): void
    {
        // Generated contract metadata is not restored; the contract record is
        // the canonical source for those values.
    }
};
