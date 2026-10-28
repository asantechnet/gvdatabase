<?php

namespace Tests\Feature;

use App\Models\AcquisitionRoute;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\EnglishLevel;
use App\Models\Position;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class VisitorProfileAndServiceContractFieldSetsTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_a_visitor_stores_its_full_profile_and_catalog_relations(): void
    {
        $this->superAdmin();
        $province = Province::create(['name' => 'Kabul', 'is_active' => true]);
        $english = EnglishLevel::create(['name' => 'B2 Upper Intermediate', 'is_active' => true]);
        $route = AcquisitionRoute::create(['name' => 'Referral', 'is_active' => true]);
        $education = EducationLevel::create(['name' => 'Bachelor', 'is_active' => true]);

        $id = $this->postJson('/api/visitors', [
            'name' => 'Profile Visitor', 'contact' => '0701234567', 'email' => 'visitor@example.test',
            'service' => 'Scholarship', 'date' => '2026-10-20',
            'education_level_id' => $education->id, 'age' => 22, 'grade_score' => 88.5,
            'province_id' => $province->id, 'english_level_id' => $english->id,
            'acquisition_route_id' => $route->id,
        ])->assertCreated()
            ->assertJsonPath('data.province.name', 'Kabul')
            ->assertJsonPath('data.english_level.name', 'B2 Upper Intermediate')
            ->assertJsonPath('data.acquisition_route.name', 'Referral')
            ->assertJsonPath('data.education_level.name', 'Bachelor')
            ->assertJsonPath('data.age', 22)
            ->assertJsonPath('data.consultant_review', null)
            ->json('data.id');

        $this->putJson("/api/visitors/{$id}", ['province_id' => null, 'grade_score' => 91])
            ->assertOk()->assertJsonPath('data.province', null)->assertJsonPath('data.grade_score', '91.00');
    }

    public function test_consultant_reviews_are_editable_by_their_dedicated_permission(): void
    {
        $counselorUser = User::factory()->create(['roles' => ['counselor']]);
        Employee::factory()->create(['user_id' => $counselorUser->id]);
        Sanctum::actingAs($counselorUser, ['access']);
        $data = ['name' => 'Review Visitor', 'contact' => '0700000001', 'service' => 'Private', 'date' => '2026-10-20'];

        $visitorId = $this->postJson('/api/visitors', [...$data, 'consultant_review' => 'great'])
            ->assertCreated()->assertJsonPath('data.consultant_review', 'great')
            ->json('data.id');

        $this->putJson("/api/visitors/{$visitorId}/consultant-review", ['consultant_review' => 'bad'])
            ->assertOk()->assertJsonPath('data.consultant_review', 'bad');

        Sanctum::actingAs(User::factory()->create(['roles' => ['staff']]), ['access']);
        $this->putJson("/api/visitors/{$visitorId}/consultant-review", ['consultant_review' => 'good'])
            ->assertForbidden();
    }

    public function test_consultant_reviews_only_accept_the_three_fixed_options(): void
    {
        $counselorUser = User::factory()->create(['roles' => ['counselor']]);
        Employee::factory()->create(['user_id' => $counselorUser->id]);
        Sanctum::actingAs($counselorUser, ['access']);
        $data = ['contact' => '0700000010', 'service' => 'Private', 'date' => '2026-10-20'];

        $this->postJson('/api/visitors', [...$data, 'name' => 'Prose Visitor', 'consultant_review' => 'Needs a scholarship comparison.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consultant_review');

        foreach (['good', 'bad', 'great'] as $review) {
            $id = $this->postJson('/api/visitors', [...$data, 'name' => "Review {$review}", 'consultant_review' => $review])
                ->assertCreated()
                ->assertJsonPath('data.consultant_review', $review)
                ->json('data.id');

            $this->putJson("/api/visitors/{$id}/consultant-review", ['consultant_review' => 'Eligible after document check.'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('consultant_review');
        }

        $this->superAdmin();
        $applicantId = $this->postJson('/api/applicants', ['name' => 'Review Applicant', 'service' => 'scholarship'])
            ->assertCreated()->json('data.id');

        $this->putJson("/api/applicants/{$applicantId}/consultant-review", ['consultant_review' => 'Prepared for the next intake.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consultant_review');
        $this->putJson("/api/applicants/{$applicantId}/consultant-review", ['consultant_review' => 'good'])
            ->assertOk()
            ->assertJsonPath('data.consultant_review', 'good');
    }

    public function test_a_reviewer_can_edit_an_applicant_consultant_review_without_case_edit_access(): void
    {
        $this->superAdmin();
        $applicantId = $this->postJson('/api/applicants', ['name' => 'Review Applicant', 'service' => 'scholarship'])
            ->assertCreated()->json('data.id');

        $counselorUser = User::factory()->create(['roles' => ['counselor']]);
        Employee::factory()->create(['user_id' => $counselorUser->id]);
        Sanctum::actingAs($counselorUser, ['access']);

        $this->putJson("/api/applicants/{$applicantId}/consultant-review", ['consultant_review' => 'great'])
            ->assertOk()->assertJsonPath('data.consultant_review', 'great');
    }

    public function test_a_consultant_cannot_move_their_visitor_to_another_consultant(): void
    {
        $other = Employee::factory()->create([
            'position_id' => Position::query()->where('system_key', 'counselor')->value('id'),
            'status' => 'active',
        ]);
        $user = User::factory()->create(['roles' => ['counselor']]);
        $self = Employee::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['access']);

        $id = $this->postJson('/api/visitors', [
            'name' => 'Locked Update', 'contact' => '0700000002', 'service' => 'Private', 'date' => '2026-10-20',
            'consultant_employee_id' => $other->id,
        ])->assertCreated()->assertJsonPath('data.consultant_employee_id', $self->id)->json('data.id');

        $this->putJson("/api/visitors/{$id}", ['consultant_employee_id' => $other->id])
            ->assertOk()->assertJsonPath('data.consultant_employee_id', $self->id);
    }

    public function test_the_new_visitor_catalogues_are_managed_through_master_data(): void
    {
        $this->superAdmin();

        foreach (['provinces' => 'Herat', 'english-levels' => 'C1 Advanced', 'acquisition-routes' => 'Instagram'] as $catalog => $name) {
            $id = $this->postJson("/api/educational-master-data/{$catalog}", ['name' => $name, 'sort_order' => 10])
                ->assertCreated()->assertJsonPath('data.name', $name)->json('data.id');
            $this->getJson("/api/educational-master-data/{$catalog}")->assertOk()->assertJsonPath('data.0.name', $name);
            $this->putJson("/api/educational-master-data/{$catalog}/{$id}", ['name' => $name.' updated'])
                ->assertOk()->assertJsonPath('data.name', $name.' updated');
            $this->deleteJson("/api/educational-master-data/{$catalog}/{$id}")->assertNoContent();
        }
    }

    public function test_a_private_contract_saves_its_application_fee_range_and_prices_at_the_maximum(): void
    {
        $this->superAdmin();
        $contract = $this->createBuiltInContract('private', [
            'fee' => 120, 'application_fee_min' => 60, 'application_fee_max' => 100,
            'application_fee' => 100, 'visa_fee' => 20,
        ]);

        $this->assertSame('60.00', $contract['services'][0]['application_fee_min']);
        $this->assertSame('100.00', $contract['services'][0]['application_fee_max']);
        $this->assertSame('100.00', $contract['services'][0]['application_fee']);
        $this->assertEquals(120, $contract['services'][0]['payable_total']);
    }

    public function test_service_field_sets_reject_missing_required_fields_and_inverted_ranges(): void
    {
        $this->superAdmin();
        $academy = $this->builtInContractPayload('academy');
        unset($academy['student_id']);
        $this->postJson('/api/contracts/academy', $academy)->assertUnprocessable()->assertJsonValidationErrors('student_id');

        $private = $this->builtInContractPayload('private');
        $private['application_fee_min'] = 200;
        $private['application_fee_max'] = 100;
        $this->postJson('/api/contracts/private', $private)->assertUnprocessable()->assertJsonValidationErrors('application_fee_max');

        $testOffer = $this->builtInContractPayload('test_offer');
        unset($testOffer['backup_phone']);
        $this->postJson('/api/contracts/test_offer', $testOffer)->assertUnprocessable()->assertJsonValidationErrors('backup_phone');
    }

    public function test_an_academy_paid_fee_creates_a_linked_income_receipt(): void
    {
        $this->superAdmin();
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $contract = $this->createBuiltInContract('academy', ['fee' => 100, 'service_fee' => 100, 'paid_fee' => 40]);
        $line = Contract::findOrFail($contract['id'])->applicantContracts()->sole();

        $this->assertSame('partially_paid', $line->payment_status);
        $this->assertEquals(40, (float) $line->amount_paid);
        $this->assertDatabaseHas('incomes', [
            'applicant_contract_id' => $line->id,
            'type' => 'Service',
            'amount' => 40,
        ]);
    }
}
