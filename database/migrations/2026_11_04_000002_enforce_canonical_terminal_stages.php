<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Make the canonical terminal stages the only terminal stages.
 *
 * The terminal outcome of a case is a product decision, not catalogue data:
 * `completed`, `cancelled`, `rejected` and `withdrawn` for the admission and
 * test-offer workflows, and the same list without `rejected` for academy.
 * Every canonical row is active and locked. Any other terminal row is demoted
 * to an ordinary stage instead of deleted, so history and foreign keys keep
 * pointing at a live row.
 *
 * Test offer gains the `rejected` outcome it was missing; a soft-deleted row
 * reserves the key, so it is restored rather than duplicated.
 */
return new class extends Migration
{
    private const CANONICAL = [
        'scholarship' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
        'private' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
        'test_offer' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
        'academy' => ['completed', 'cancelled', 'withdrawn'],
    ];

    public function up(): void
    {
        $this->ensureTestOfferRejection();

        foreach (self::CANONICAL as $service => $keys) {
            DB::table('applicant_stages')
                ->where('service', $service)
                ->whereIn('key', $keys)
                ->whereNull('deleted_at')
                ->update(['is_terminal' => true, 'is_locked' => true, 'is_active' => true]);
        }

        $demoted = DB::table('applicant_stages')
            ->where('is_terminal', true)
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                foreach (self::CANONICAL as $service => $keys) {
                    $query->orWhere(fn ($row) => $row
                        ->where('service', $service)
                        ->whereNotIn('key', $keys));
                }
            })
            ->update(['is_terminal' => false, 'is_locked' => false]);

        if ($demoted > 0) {
            Log::warning("Demoted {$demoted} non-canonical terminal applicant stages to ordinary stages.");
        }
    }

    public function down(): void
    {
        // Only the row this migration introduced is removable, and only while
        // no case or history row references it. Demoted custom terminals keep
        // their new flags because their previous values were not recorded.
        $id = DB::table('applicant_stages')
            ->where('service', 'test_offer')->where('key', 'rejected')
            ->value('id');

        if (! $id) {
            return;
        }

        $referenced = DB::table('applicants')->where('current_stage_id', $id)->exists()
            || DB::table('applicant_contract')->where('current_stage_id', $id)->exists()
            || DB::table('applicant_stage_histories')
                ->where(fn ($query) => $query->where('new_stage_id', $id)->orWhere('old_stage_id', $id))
                ->exists();

        if (! $referenced) {
            DB::table('applicant_stages')->where('id', $id)->delete();
        }
    }

    private function ensureTestOfferRejection(): void
    {
        $existing = DB::table('applicant_stages')
            ->where('service', 'test_offer')->where('key', 'rejected')
            ->first();

        if ($existing) {
            if ($existing->deleted_at !== null) {
                DB::table('applicant_stages')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'is_terminal' => true,
                    'is_locked' => true,
                    'is_active' => true,
                    'allowed_next_keys' => json_encode([]),
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $order = (int) DB::table('applicant_stages')->where('service', 'test_offer')->max('sort_order');

        DB::table('applicant_stages')->insert([
            'service' => 'test_offer',
            'key' => 'rejected',
            'name' => 'Rejected',
            'sort_order' => $order + 10,
            'is_active' => true,
            'is_terminal' => true,
            'is_locked' => true,
            'allowed_next_keys' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
