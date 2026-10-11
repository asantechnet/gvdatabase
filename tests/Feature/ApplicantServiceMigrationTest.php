<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Country;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicantServiceMigrationTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        // In-memory SQLite needs a fresh schema when mixing transaction and
        // truncation tests; the latter deliberately runs DDL outside a transaction.
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    public function test_cutover_backfills_legacy_and_deleted_applicants_without_overwriting_details(): void
    {
        $migration = require database_path('migrations/2026_10_07_000007_move_applicant_fields_to_service_details.php');
        $migration->down();
        $country = Country::create(['name' => 'Migration Destination', 'code' => 'MD']);
        $legacy = Applicant::factory()->create();
        $deleted = Applicant::factory()->create();
        $deleted->delete();
        $existing = Applicant::factory()->create();
        $existing->scholarshipDetail()->create(['country_id' => null, 'grade_score' => 940]);
        $academy = Applicant::factory()->create(['service' => 'academy']);
        DB::table('applicants')->whereIn('id', [$legacy->id, $deleted->id, $existing->id, $academy->id])
            ->update(['country_id' => $country->id, 'percentage' => 82.5]);

        $migration->up();

        $this->assertDatabaseHas('scholarship_applicant_details', ['applicant_id' => $legacy->id, 'country_id' => $country->id, 'grade_score' => 82.5]);
        $this->assertDatabaseHas('scholarship_applicant_details', ['applicant_id' => $existing->id, 'country_id' => null, 'grade_score' => 940]);
        $this->assertDatabaseHas('scholarship_applicant_details', ['applicant_id' => $deleted->id, 'country_id' => $country->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('applicants', ['id' => $deleted->id]);
        $deleted->restore();
        $this->assertSame($country->id, $deleted->fresh()->scholarshipDetail->country_id);
        $this->assertDatabaseMissing('scholarship_applicant_details', ['applicant_id' => $academy->id]);
        foreach (['country_id', 'university_id', 'education_level_id', 'percentage', 'language_id', 'semester_id', 'application_date', 'date'] as $column) {
            $this->assertFalse(Schema::hasColumn('applicants', $column));
        }
        $this->assertTrue(Schema::hasColumn('applicants', 'parent_applicant_id'));
    }
}
