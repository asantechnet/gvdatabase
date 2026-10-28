<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counselor reviews used to be free text (the older boolean sign-off was
 * migrated to the note "Reviewed"). They are now a fixed three-point choice,
 * so every stored value has to fit that set before validation starts rejecting
 * everything else.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const VALUES = ['good', 'bad', 'great'];

    public function up(): void
    {
        foreach (['visitors', 'applicants'] as $table) {
            if (! Schema::hasColumn($table, 'consultant_review')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('consultant_review')
                ->select(['id', 'consultant_review'])
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        $review = $this->canonical($row->consultant_review);

                        if ($review === $row->consultant_review) {
                            continue;
                        }

                        DB::table($table)->where('id', $row->id)->update(['consultant_review' => $review]);
                    }
                });
        }
    }

    private function canonical(?string $review): ?string
    {
        $normalized = strtolower(trim((string) $review));

        if (in_array($normalized, self::VALUES, true)) {
            return $normalized;
        }

        // The former boolean "reviewed by consultant" sign-off.
        if ($normalized === 'reviewed' || str_contains($normalized, 'review completed')) {
            return 'good';
        }

        // Free prose cannot be represented by a three-point choice.
        return null;
    }

    public function down(): void
    {
        // The original prose cannot be reconstructed; the three-option values
        // remain valid data for the previous schema.
    }
};
