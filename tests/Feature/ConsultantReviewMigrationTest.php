<?php

namespace Tests\Feature;

use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsultantReviewMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_normalizes_legacy_reviews_to_the_three_fixed_options(): void
    {
        $visitor = fn (string $review): int => DB::table('visitors')->insertGetId([
            'name' => 'Legacy '.$review,
            'contact' => '0700000099',
            'service' => 'Private',
            'date' => '2026-10-20',
            'consultant_review' => $review,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reviewed = $visitor('Reviewed');
        $prose = $visitor('Needs a scholarship comparison.');
        $uppercase = $visitor('GREAT');
        $applicant = Applicant::factory()->create(['consultant_review' => 'Initial counseling review completed.']);

        // The multi-tenancy product keeps office-scoped migrations in a
        // separate `office` set; main keeps them in the default directory.
        $migration = database_path('migrations/2026_10_28_000001_restrict_consultant_reviews_to_three_options.php');
        if (! is_file($migration)) {
            $migration = database_path('migrations/office/2026_10_28_000001_restrict_consultant_reviews_to_three_options.php');
        }

        (require $migration)->up();

        $this->assertSame('good', DB::table('visitors')->where('id', $reviewed)->value('consultant_review'));
        $this->assertNull(DB::table('visitors')->where('id', $prose)->value('consultant_review'));
        $this->assertSame('great', DB::table('visitors')->where('id', $uppercase)->value('consultant_review'));
        $this->assertSame('good', $applicant->fresh()->consultant_review);
    }
}
