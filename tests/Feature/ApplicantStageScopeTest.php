<?php

namespace Tests\Feature;

use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\Role;
use App\Models\Test;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantStageScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_scope_belongs_only_to_the_service_that_uses_it(): void
    {
        $test = Test::create(['name' => 'IELTS', 'is_active' => true]);
        $country = Country::create(['name' => 'Destination', 'code' => 'DST']);
        $university = University::create(['name' => 'Destination University', 'country_id' => $country->id]);

        $testOffer = $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'ielts_speaking', 'name' => 'IELTS speaking',
            'test_id' => $test->id, 'country_id' => $country->id, 'university_id' => $university->id,
        ])->assertCreated()->json('data');
        $this->assertSame($test->id, $testOffer['test_id']);
        $this->assertNull($testOffer['country_id']);
        $this->assertNull($testOffer['university_id']);

        $academy = $this->postJson('/api/applicant-stages', [
            'service' => 'academy', 'key' => 'evening_class', 'name' => 'Evening class',
            'test_id' => $test->id, 'country_id' => $country->id, 'university_id' => $university->id,
        ])->assertCreated()->json('data');
        $this->assertNull($academy['test_id']);
        $this->assertNull($academy['country_id']);
        $this->assertNull($academy['university_id']);

        $scholarship = $this->postJson('/api/applicant-stages', [
            'service' => 'scholarship', 'key' => 'embassy_interview', 'name' => 'Embassy interview',
            'test_id' => $test->id, 'country_id' => $country->id,
        ])->assertCreated()->json('data');
        $this->assertNull($scholarship['test_id']);
        $this->assertSame($country->id, $scholarship['country_id']);
    }

    public function test_an_admission_stage_picker_is_scoped_to_the_selected_destination(): void
    {
        $canada = Country::create(['name' => 'Canada', 'code' => 'CAN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $toronto = University::create(['name' => 'Toronto', 'country_id' => $canada->id]);
        $ankara = University::create(['name' => 'Ankara', 'country_id' => $turkey->id]);

        foreach ([
            ['canada_extra', $canada->id, null],
            ['toronto_extra', $canada->id, $toronto->id],
            ['turkey_extra', $turkey->id, null],
            ['ankara_extra', $turkey->id, $ankara->id],
        ] as [$key, $countryId, $universityId]) {
            ApplicantStage::create([
                'service' => 'scholarship', 'key' => $key, 'name' => $key,
                'country_id' => $countryId, 'university_id' => $universityId, 'is_active' => true,
            ]);
        }

        $keys = fn (string $query) => array_column(
            $this->getJson('/api/applicant-stages?service=scholarship'.$query)->assertOk()->json('data'),
            'key',
        );

        $global = $keys('');
        $this->assertContains('new', $global);
        $this->assertNotContains('canada_extra', $global);
        $this->assertNotContains('toronto_extra', $global);

        $byCountry = $keys("&country_id={$canada->id}");
        $this->assertContains('canada_extra', $byCountry);
        $this->assertNotContains('toronto_extra', $byCountry);
        $this->assertNotContains('turkey_extra', $byCountry);

        // A university implies its country, matching the pipeline context.
        $byUniversity = $keys("&university_id={$toronto->id}");
        $this->assertContains('canada_extra', $byUniversity);
        $this->assertContains('toronto_extra', $byUniversity);
        $this->assertNotContains('ankara_extra', $byUniversity);
    }

    public function test_a_test_offer_picker_returns_shared_and_the_selected_test_only(): void
    {
        $ielts = Test::create(['name' => 'IELTS', 'is_active' => true]);
        $toefl = Test::create(['name' => 'TOEFL', 'is_active' => true]);

        foreach ([['shared_docs', null], ['ielts_review', $ielts->id], ['toefl_review', $toefl->id]] as [$key, $testId]) {
            ApplicantStage::create([
                'service' => 'test_offer', 'key' => $key, 'name' => $key,
                'test_id' => $testId, 'is_active' => true,
            ]);
        }

        // Without a test, a picker offers only the shared baseline.
        $shared = array_column($this->getJson('/api/applicant-stages?service=test_offer')->assertOk()->json('data'), 'key');
        $this->assertContains('shared_docs', $shared);
        $this->assertNotContains('ielts_review', $shared);
        $this->assertNotContains('toefl_review', $shared);

        $selected = array_column(
            $this->getJson('/api/applicant-stages?service=test_offer&test_id='.$ielts->id)->assertOk()->json('data'),
            'key',
        );
        $this->assertContains('shared_docs', $selected);
        $this->assertContains('ielts_review', $selected);
        $this->assertNotContains('toefl_review', $selected);

        // The editor read keeps the whole catalogue so the scope is visible.
        $editor = array_column(
            $this->getJson('/api/applicant-stages?service=test_offer&all=1')->assertOk()->json('data'),
            'key',
        );
        $this->assertContains('ielts_review', $editor);
        $this->assertContains('toefl_review', $editor);
    }

    public function test_a_test_scope_must_reference_a_live_test(): void
    {
        $test = Test::create(['name' => 'DELF', 'is_active' => true]);
        $test->delete();

        $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'delf_review', 'name' => 'DELF review', 'test_id' => $test->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('test_id');

        $this->assertDatabaseMissing('applicant_stages', ['service' => 'test_offer', 'key' => 'delf_review']);
    }

    public function test_a_test_referenced_by_a_stage_cannot_be_deleted(): void
    {
        $referenced = Test::create(['name' => 'Referenced test', 'is_active' => true]);
        ApplicantStage::create([
            'service' => 'test_offer', 'key' => 'referenced_stage', 'name' => 'Referenced stage',
            'test_id' => $referenced->id, 'is_active' => true,
        ]);

        $this->deleteJson('/api/educational-master-data/tests/'.$referenced->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.master_data_in_use');
        $this->assertNull($referenced->fresh()->deleted_at);

        $unused = Test::create(['name' => 'Unused test', 'is_active' => true]);
        $this->deleteJson('/api/educational-master-data/tests/'.$unused->id)->assertNoContent();
    }

    public function test_a_soft_deleted_stage_still_blocks_test_deletion(): void
    {
        $test = Test::create(['name' => 'Deleted stage test', 'is_active' => true]);
        $stage = ApplicantStage::create([
            'service' => 'test_offer', 'key' => 'deleted_stage', 'name' => 'Deleted stage',
            'test_id' => $test->id, 'is_active' => true,
        ]);
        $stage->delete();

        $this->deleteJson('/api/educational-master-data/tests/'.$test->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.master_data_in_use');
    }
}
