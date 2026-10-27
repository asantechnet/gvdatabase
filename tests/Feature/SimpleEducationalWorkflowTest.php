<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Country;
use App\Models\EducationalScholarship;
use App\Models\Subject;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class SimpleEducationalWorkflowTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_private_admission_persists_details_subjects_charges_and_immutable_snapshot(): void
    {
        $country = Country::create(['name' => 'Canada', 'code' => 'CA']);
        $subject = Subject::create(['name' => 'Computer Science']);
        $contract = $this->createBuiltInContract('private', [
            'name' => 'Private applicant', 'father_name' => 'Guardian', 'father_phone' => '0701234567',
            'grade_score' => 90, 'country_id' => $country->id, 'university_fee' => 12000,
            'favorite_subject_ids' => [$subject->id], 'notes' => 'Admission note',
            'fee' => 150, 'application_fee_min' => 80, 'application_fee_max' => 100,
            'application_fee' => 100, 'visa_fee' => 50,
        ]);
        $this->assertSame('100.00', $contract['services'][0]['application_fee']);
        $this->assertSame('80.00', $contract['services'][0]['application_fee_min']);
        $this->assertSame('100.00', $contract['services'][0]['application_fee_max']);
        $this->assertSame('50.00', $contract['services'][0]['visa_fee']);
        $this->assertSame('90.00', $contract['snapshot_data']['entity']['details']['grade_score']);
        $this->assertDatabaseHas('private_applicant_details', ['applicant_id' => $contract['applicant_id'], 'country_id' => $country->id]);
        $this->getJson('/api/applicants?service=private&country_id='.$country->id)
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.favorite_subject_ids.0', $subject->id);
        $this->getJson('/api/educational-dashboard')->assertOk()->assertJsonPath('data.countries.0.total', 1);
        $this->getJson('/api/educational-reports/applicants?country_id='.$country->id)
            ->assertOk()->assertJsonPath('data.rows.0.country', 'Canada');
        $this->getJson('/api/educational-reports/services?country_id='.$country->id)
            ->assertOk()->assertJsonPath('data.rows.0.service', 'private')->assertJsonPath('data.rows.0.country', 'Canada');
        $this->putJson('/api/applicants/'.$contract['applicant_id'], ['grade_score' => 95])->assertOk();
        $this->getJson('/api/contracts/'.$contract['id'])->assertOk()
            ->assertJsonPath('data.snapshot_data.entity.details.grade_score', '90.00');
    }

    public function test_missing_scholarship_class_and_backup_phone_round_trip_on_create_and_edit(): void
    {
        $scholarship = EducationalScholarship::create(['name' => 'Merit scholarship']);
        $class = AcademyClass::create(['name' => 'Morning class']);
        $scholar = $this->postJson('/api/applicants', ['name' => 'Scholar', 'scholarship_id' => $scholarship->id])->assertCreated()->json('data.id');
        $this->getJson('/api/applicants/'.$scholar)->assertOk()->assertJsonPath('data.details.scholarship.name', 'Merit scholarship');
        $academy = $this->postJson('/api/applicants', ['name' => 'Student', 'service' => 'academy', 'class_id' => $class->id])->assertCreated()->json('data.id');
        $this->getJson('/api/applicants/'.$academy)->assertOk()->assertJsonPath('data.details.academy_class.name', 'Morning class');
        $this->putJson('/api/applicants/'.$academy, ['class_id' => null])->assertOk()->assertJsonPath('data.details.class_id', null);
        $this->postJson('/api/applicants', ['name' => 'Test student', 'service' => 'test_offer', 'backup_phone' => '0700000000'])
            ->assertCreated()->assertJsonPath('data.backup_phone', '0700000000');
        $this->deleteJson('/api/educational-master-data/scholarships/'.$scholarship->id)->assertStatus(409);
    }

    public function test_itemised_charges_cannot_disagree_with_total_or_leak_between_services(): void
    {
        $payload = $this->builtInContractPayload('test_offer', ['fee' => 60, 'booking_fee' => 20, 'service_fee' => 30, 'remote_fee' => 10]);
        $this->postJson('/api/contracts/test_offer', $payload)->assertCreated()
            ->assertJsonPath('data.services.0.booking_fee', '20.00');
        $this->postJson('/api/contracts/test_offer', [...$payload, 'fee' => 100])->assertUnprocessable()->assertJsonValidationErrors('fee');
        $this->postJson('/api/contracts/test_offer', [...$payload, 'visa_fee' => 50])->assertUnprocessable()->assertJsonValidationErrors('visa_fee');
        $this->postJson('/api/applicants', ['name' => 'Invalid score', 'grade_score' => 101])->assertUnprocessable()->assertJsonValidationErrors('grade_score');
    }

    public function test_date_filters_are_inclusive_validated_and_allow_a_single_bound(): void
    {
        Applicant::factory()->create(['created_at' => '2026-10-01 00:00:00']);
        Applicant::factory()->create(['created_at' => '2026-10-02 23:59:59']);
        Applicant::factory()->create(['created_at' => '2026-10-03 00:00:00']);
        $this->getJson('/api/applicants?date_from=2026-10-01&date_to=2026-10-02')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/applicants?date_to=2026-10-02')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/applicants?date_from=2026-10-03&date_to=2026-10-01')->assertUnprocessable();
        $this->getJson('/api/contracts?date_from=invalid')->assertUnprocessable();
        Visitor::create(['name' => 'In range', 'contact' => '0701111111', 'service' => 'Academy', 'date' => '2026-10-02']);
        Visitor::create(['name' => 'Outside range', 'contact' => '0702222222', 'service' => 'Academy', 'date' => '2026-10-03']);
        $this->getJson('/api/visitors?date_to=2026-10-02')->assertOk()->assertJsonCount(1, 'data');
        $inRange = $this->createBuiltInContract('academy');
        $outside = $this->createBuiltInContract('academy');
        Contract::findOrFail($inRange['id'])->update(['start_date' => '2026-10-02']);
        Contract::findOrFail($outside['id'])->update(['start_date' => '2026-10-03']);
        $this->getJson('/api/contracts?service=academy&date_from=2026-10-02&date_to=2026-10-02')->assertOk()->assertJsonCount(1, 'data');
    }
}
