<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Country;
use App\Models\Duration;
use App\Models\Program;
use App\Models\Role;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Test;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ApplicantServiceDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Each built-in service asks for a different applicant field set. These tests
 * pin the service discriminator, the matching detail row, and the pivots that
 * carry the multi-valued fields.
 */
class ApplicantServiceDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_a_scholarship_applicant_stores_its_own_fields_and_favourite_subjects(): void
    {
        $mathematics = Subject::create(['name' => 'Mathematics']);
        $physics = Subject::create(['name' => 'Physics']);

        $applicant = $this->postJson('/api/applicants', [
            'name' => 'Scholarship Applicant',
            'service' => 'scholarship',
            'passport_id' => 'P-100',
            'grade_score' => 88.5,
            'university_fee' => 12000,
            'favorite_subject_ids' => [$mathematics->id, $physics->id],
        ])->assertCreated()->json('data');

        $this->assertSame('scholarship', $applicant['service']);
        foreach (['country_id', 'university_id', 'education_level_id', 'percentage', 'language_id', 'semester_id', 'application_date', 'date'] as $field) {
            $this->assertArrayNotHasKey($field, $applicant);
        }
        $this->assertSame('P-100', $applicant['details']['passport_id']);
        $this->assertDatabaseHas('scholarship_applicant_details', [
            'applicant_id' => $applicant['id'],
            'passport_id' => 'P-100',
        ]);
        $this->assertDatabaseHas('scholarship_applicant_subject', [
            'applicant_id' => $applicant['id'],
            'subject_id' => $mathematics->id,
        ]);
    }

    public function test_a_test_offer_applicant_stores_its_test_and_skills(): void
    {
        $test = Test::create(['name' => 'IELTS']);
        $writing = Skill::create(['name' => 'Writing']);

        $applicant = $this->postJson('/api/applicants', [
            'name' => 'Test Applicant',
            'service' => 'test_offer',
            'passport_id' => 'P-200',
            'test_id' => $test->id,
            'skill_ids' => [$writing->id],
        ])->assertCreated()->json('data');

        $this->assertSame('test_offer', $applicant['service']);
        $this->assertSame([$writing->id], $applicant['skill_ids']);
        $this->assertSame($test->id, $applicant['details']['test_id']);
        $this->assertDatabaseHas('test_applicant_details', [
            'applicant_id' => $applicant['id'],
            'test_id' => $test->id,
        ]);
        $this->assertDatabaseHas('test_applicant_skill', [
            'applicant_id' => $applicant['id'],
            'skill_id' => $writing->id,
        ]);
    }

    public function test_an_academy_applicant_stores_program_time_duration_and_teachers(): void
    {
        $program = Program::create(['name' => 'Foundation']);
        $time = TimeSlot::create(['name' => 'Morning']);
        $duration = Duration::create(['name' => '3 months']);
        $teacher = Teacher::create(['name' => 'Teacher One', 'contact' => '+93700000000']);

        $applicant = $this->postJson('/api/applicants', [
            'name' => 'Academy Applicant',
            'service' => 'academy',
            'father_name' => 'Applicant Father',
            'program_id' => $program->id,
            'time_id' => $time->id,
            'duration_id' => $duration->id,
            'teacher_ids' => [$teacher->id],
        ])->assertCreated()->json('data');

        $this->assertSame('academy', $applicant['service']);
        $this->assertSame([$teacher->id], $applicant['teacher_ids']);
        $this->assertSame($program->id, $applicant['details']['program_id']);
        $this->assertSame($time->id, $applicant['details']['time_id']);
        $this->assertSame($duration->id, $applicant['details']['duration_id']);
        $this->assertDatabaseHas('academy_applicant_teacher', [
            'applicant_id' => $applicant['id'],
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_a_catalogue_row_referenced_by_an_applicant_cannot_be_deleted(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);

        $this->postJson('/api/applicants', [
            'name' => 'Scholarship Applicant',
            'service' => 'scholarship',
            'favorite_subject_ids' => [$subject->id],
        ])->assertCreated();

        $this->deleteJson("/api/educational-master-data/subjects/{$subject->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.master_data_in_use');

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_updating_an_applicant_rewrites_the_detail_row_and_its_selections(): void
    {
        $first = Skill::create(['name' => 'Listening']);
        $second = Skill::create(['name' => 'Speaking']);

        $applicant = $this->postJson('/api/applicants', [
            'name' => 'Test Applicant',
            'service' => 'test_offer',
            'skill_ids' => [$first->id],
        ])->assertCreated()->json('data');

        $this->putJson("/api/applicants/{$applicant['id']}", [
            'passport_id' => 'P-300',
            'skill_ids' => [$second->id],
        ])->assertOk()->assertJsonPath('data.details.passport_id', 'P-300');

        $this->assertDatabaseMissing('test_applicant_skill', [
            'applicant_id' => $applicant['id'],
            'skill_id' => $first->id,
        ]);
        $this->assertDatabaseHas('test_applicant_skill', [
            'applicant_id' => $applicant['id'],
            'skill_id' => $second->id,
        ]);
    }

    public function test_destination_only_updates_sync_details_and_respect_explicit_null(): void
    {
        $first = Country::create(['name' => 'First Destination', 'code' => 'FD']);
        $second = Country::create(['name' => 'Second Destination', 'code' => 'SD']);
        $id = $this->postJson('/api/applicants', [
            'name' => 'Scholarship Applicant', 'country_id' => $first->id,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/applicants/{$id}", ['country_id' => $second->id])
            ->assertOk()->assertJsonPath('data.details.country_id', $second->id);
        $this->putJson("/api/applicants/{$id}", ['country_id' => null])
            ->assertOk()->assertJsonPath('data.details.country_id', null);
        $this->putJson("/api/applicants/{$id}", ['passport_id' => 'P-400'])
            ->assertOk()->assertJsonPath('data.details.country_id', null);
    }

    public function test_scoped_stage_validation_uses_service_details(): void
    {
        $country = Country::create(['name' => 'Destination', 'code' => 'DS']);
        $applicant = Applicant::create(['name' => 'Scholarship Applicant', 'service' => 'scholarship']);
        $applicant->scholarshipDetail()->create(['country_id' => $country->id]);
        $stage = ApplicantStage::create([
            'name' => 'Destination Review', 'key' => 'destination_review',
            'country_id' => $country->id, 'is_active' => true,
        ]);

        // The stage gate needs a labeled document before the case moves forward.
        $this->seedApplicantDocuments($applicant);

        $this->postJson("/api/applicants/{$applicant->id}/change-stage", ['stage_id' => $stage->id])
            ->assertOk()->assertJsonPath('data.current_stage_id', $stage->id);

        $applicant->update(['service' => 'academy']);
        $this->postJson("/api/applicants/{$applicant->id}/change-stage", ['stage_id' => $stage->id])
            ->assertUnprocessable();
    }

    public function test_a_failed_detail_write_rolls_back_applicant_creation(): void
    {
        $writer = Mockery::mock(ApplicantServiceDetails::class);
        $writer->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Detail write failed'));
        $this->app->instance(ApplicantServiceDetails::class, $writer);
        $before = Applicant::count();
        $historyBefore = ApplicantStageHistory::count();

        $this->postJson('/api/applicants', ['name' => 'Atomic Applicant'])->assertStatus(500);

        $this->assertSame($before, Applicant::count());
        $this->assertSame($historyBefore, ApplicantStageHistory::count());
    }

    public function test_service_filter_is_shared_by_list_and_pipeline(): void
    {
        $this->postJson('/api/applicants', ['name' => 'Scholarship Applicant'])->assertCreated();
        $this->postJson('/api/applicants', ['name' => 'Academy Applicant', 'service' => 'academy'])->assertCreated();

        $this->getJson('/api/applicants?service=academy')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.service', 'academy');
        $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()
            ->assertJsonPath('data.totals.all', 1);
    }

    public function test_a_failed_detail_write_rolls_back_identity_changes_on_update(): void
    {
        $id = $this->postJson('/api/applicants', ['name' => 'Original Name'])
            ->assertCreated()->json('data.id');
        $writer = Mockery::mock(ApplicantServiceDetails::class);
        $writer->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Detail write failed'));
        $this->app->instance(ApplicantServiceDetails::class, $writer);

        $this->putJson("/api/applicants/{$id}", ['name' => 'Changed Name', 'passport_id' => 'P-500'])
            ->assertStatus(500);

        $this->assertDatabaseHas('applicants', ['id' => $id, 'name' => 'Original Name']);
        $this->assertDatabaseMissing('scholarship_applicant_details', ['applicant_id' => $id, 'passport_id' => 'P-500']);
    }

    public function test_an_omitted_selection_is_preserved_and_an_empty_selection_clears_it(): void
    {
        $skill = Skill::create(['name' => 'Reading']);
        $id = $this->postJson('/api/applicants', [
            'name' => 'Test Applicant', 'service' => 'test_offer', 'skill_ids' => [$skill->id],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/applicants/{$id}", ['passport_id' => 'P-600'])
            ->assertOk()->assertJsonPath('data.skill_ids', [$skill->id]);
        $this->putJson("/api/applicants/{$id}", ['skill_ids' => []])
            ->assertOk()->assertJsonPath('data.skill_ids', []);
        $this->assertDatabaseMissing('test_applicant_skill', ['applicant_id' => $id]);
    }

    public function test_a_partial_edit_preserves_the_migrated_destination(): void
    {
        $country = Country::create(['name' => 'Legacy Destination', 'code' => 'LD']);
        $applicant = Applicant::create([
            'name' => 'Legacy Applicant', 'service' => 'scholarship',
        ]);
        $applicant->scholarshipDetail()->create(['country_id' => $country->id]);

        $this->putJson("/api/applicants/{$applicant->id}", ['passport_id' => 'P-700'])
            ->assertOk()->assertJsonPath('data.details.country_id', $country->id);
        $this->putJson("/api/applicants/{$applicant->id}", ['passport_id' => 'P-701'])
            ->assertOk()->assertJsonPath('data.details.country_id', $country->id);
    }
}
