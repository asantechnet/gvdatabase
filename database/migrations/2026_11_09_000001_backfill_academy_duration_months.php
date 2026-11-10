<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recover the month count for academy durations whose name already says it.
 *
 * The academy term is the earlier of the class end date and the sold
 * duration's month count. The first backfill ran before the reference seeder
 * created the standard `3 Months` / `6 Months` rows, so those rows reached
 * production with a null month count and academy contracts could not get a
 * term. This idempotent pass only fills nulls with values already present in
 * the name; unknown names stay null and are never invented.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('durations')
            ->whereNull('months')
            ->orderBy('id')
            ->chunkById(200, function ($durations): void {
                foreach ($durations as $duration) {
                    if (! preg_match('/^(\d{1,2})\s*months?$/i', trim((string) $duration->name), $matches)) {
                        continue;
                    }

                    $months = (int) $matches[1];
                    if ($months >= 1 && $months <= 60) {
                        DB::table('durations')->where('id', $duration->id)->update(['months' => $months]);
                    }
                }
            });
    }

    public function down(): void
    {
        // A recovered month count is real catalogue data; do not erase it.
    }
};
