<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Email verification did not exist in the prior login flow. Preserve
        // access for already-established accounts while requiring verification
        // for every account created after this migration.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Verification timestamps may have become genuine after deployment;
        // they cannot be safely distinguished from this compatibility backfill.
    }
};
