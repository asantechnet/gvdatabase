<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ApplicantPipelineTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_legacy_unstaged_applicants_are_repaired_but_stay_off_the_line_only_pipeline(): void
    {
        $applicant = Applicant::factory()->create(['current_stage_id' => null]);
        $migration = require database_path('migrations/2026_10_08_000003_repair_new_applicant_stage.php');
        $migration->up();
        $migration->up();
        $new = ApplicantStage::where('key', 'new')->whereNull('country_id')->whereNull('university_id')->firstOrFail();
        $this->assertSame($new->id, $applicant->fresh()->current_stage_id);

        // The applicant is a party without a sold service line, so the service
        // case board has no card for it; the applications list still holds it.
        $board = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(0, $board['totals']['all']);
        $this->assertSame([], $board['cards']);

        // The unlined applicant remains a case at the applicant level.
        $consultation = ApplicantStage::where('key', 'consultation')->where('service', 'scholarship')->firstOrFail();
        $this->postJson('/api/applicants/'.$applicant->id.'/change-stage', ['stage_id' => $consultation->id])
            ->assertOk()->assertJsonPath('data.current_stage_id', $consultation->id);
        $this->assertDatabaseHas('applicant_stage_histories', [
            'applicant_id' => $applicant->id, 'old_stage_id' => $new->id, 'new_stage_id' => $consultation->id,
        ]);
    }

    public function test_list_supports_search_filters_and_boards(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Ahmad Ahmadi', 'country_id' => $iran->id, 'responsible_employee_id' => $employee->id])->assertCreated()->json('data');
        $b = $this->postJson('/api/applicants', ['name' => 'Sara Mohammadi', 'country_id' => $turkey->id])->assertCreated()->json('data');

        // Search by applicant name.
        $this->getJson('/api/applicants?search=Ahmad')->assertOk()->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => $a['id']]);

        // Filter by country and employee.
        $this->getJson("/api/applicants?country_id={$iran->id}")->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/applicants?responsible_employee_id={$employee->id}")->assertOk()->assertJsonPath('data.total', 1);

        // Operation board holds both active cases on the party list.
        $this->getJson('/api/applicants?board=operation')->assertOk()->assertJsonPath('data.total', 2);

        // Move one case to the completed stage directly in storage (terminal
        // stages are not reachable through linear transitions by design).
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();
        Applicant::query()->whereKey($b['id'])->update(['current_stage_id' => $completed->id, 'status' => 'closed']);

        $this->getJson('/api/applicants?board=operation')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/applicants?board=final')->assertOk()->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => $b['id']]);
        $this->getJson('/api/applicants?status=closed')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_pipeline_counts_respect_active_filters(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);

        $this->createBuiltInContract('scholarship', ['name' => 'Case 1', 'country_id' => $iran->id]);
        $this->createBuiltInContract('scholarship', ['name' => 'Case 2', 'country_id' => $turkey->id]);

        $all = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(2, $all['totals']['all']);
        $this->assertSame(2, $all['totals']['active']);
        $this->assertSame(array_sum(array_column($all['stages'], 'count')), $all['totals']['all']);
        $this->assertSame(2, $all['counts']['cards']);
        $this->assertSame('service_case', $all['counts']['unit']);

        $filtered = $this->getJson("/api/applicants-pipeline?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['totals']['all']);
        $this->assertSame(1, array_sum(array_column($filtered['stages'], 'count')));
        $this->assertCount(1, $filtered['cards']);
        $this->assertSame('Case 1', $filtered['cards'][0]['name']);
        $this->assertNotEmpty($filtered['stages']);
        $this->assertArrayHasKey('key', $filtered['stages'][0]);
        $this->assertArrayHasKey('allowed_next_keys', $filtered['stages'][0]);
    }

    public function test_pipeline_stage_columns_follow_the_destination_filter(): void
    {
        $canada = Country::create(['name' => 'Canada', 'code' => 'CAN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $toronto = University::create(['name' => 'Toronto', 'country_id' => $canada->id]);
        $ankara = University::create(['name' => 'Ankara', 'country_id' => $turkey->id]);

        ApplicantStage::create([
            'service' => 'scholarship', 'key' => 'canada_extra', 'name' => 'Canada extra',
            'country_id' => $canada->id, 'sort_order' => 15, 'is_active' => true,
        ]);
        ApplicantStage::create([
            'service' => 'scholarship', 'key' => 'toronto_extra', 'name' => 'Toronto extra',
            'country_id' => $canada->id, 'university_id' => $toronto->id, 'sort_order' => 16, 'is_active' => true,
        ]);
        ApplicantStage::create([
            'service' => 'scholarship', 'key' => 'turkey_extra', 'name' => 'Turkey extra',
            'country_id' => $turkey->id, 'sort_order' => 17, 'is_active' => true,
        ]);

        $keys = fn (string $query) => array_column(
            $this->getJson('/api/applicants-pipeline?service=scholarship'.$query)->assertOk()->json('data.stages'),
            'key',
        );

        $unscoped = $keys('');
        $this->assertContains('new', $unscoped);
        $this->assertNotContains('canada_extra', $unscoped);
        $this->assertNotContains('toronto_extra', $unscoped);
        $this->assertNotContains('turkey_extra', $unscoped);

        $country = $keys("&country_id={$canada->id}");
        $this->assertContains('canada_extra', $country);
        $this->assertNotContains('toronto_extra', $country);
        $this->assertNotContains('turkey_extra', $country);

        // A university implies its country and adds the university-only stage.
        $university = $keys("&university_id={$toronto->id}");
        $this->assertContains('canada_extra', $university);
        $this->assertContains('toronto_extra', $university);
        $this->assertNotContains('turkey_extra', $university);

        $otherUniversity = $keys("&university_id={$ankara->id}");
        $this->assertContains('turkey_extra', $otherUniversity);
        $this->assertNotContains('canada_extra', $otherUniversity);
        $this->assertNotContains('toronto_extra', $otherUniversity);
    }

    public function test_pipeline_and_list_require_view_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/applicants')->assertForbidden();
        $this->getJson('/api/applicants-pipeline')->assertForbidden();
    }

    public function test_illegal_stage_jump_is_rejected(): void
    {
        $a = $this->postJson('/api/applicants', ['name' => 'Stage Case'])->assertCreated()->json('data');
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();

        $this->postJson("/api/applicants/{$a['id']}/change-stage", ['stage_id' => $completed->id])->assertUnprocessable();
    }

    public function test_cancel_withdraw_reject_and_appeal_paths_are_reachable(): void
    {
        $stageId = fn (string $key) => ApplicantStage::query()->where('key', $key)->where('service', 'scholarship')->firstOrFail()->id;
        // Every move carries a note; terminal moves require one.
        $move = fn (int $id, string $key) => $this->postJson("/api/applicants/{$id}/change-stage", ['stage_id' => $stageId($key), 'note' => "Move to {$key}"])->assertOk();

        // Cancel from an early active stage; withdrawn case re-engages.
        $a = $this->postJson('/api/applicants', ['name' => 'Cancel Case'])->assertCreated()->json('data');
        $move($a['id'], 'consultation');
        $move($a['id'], 'cancelled');
        $this->getJson('/api/applicants?board=cancelled')->assertOk()->assertJsonFragment(['id' => $a['id']]);

        $b = $this->postJson('/api/applicants', ['name' => 'Withdraw Case'])->assertCreated()->json('data');
        $move($b['id'], 'consultation');
        $move($b['id'], 'withdrawn');
        $moved = $move($b['id'], 'consultation');
        $this->assertSame('active', $moved->json('data.status'));

        // Walk to a decision stage, record a refusal, then appeal.
        $c = $this->postJson('/api/applicants', ['name' => 'Appeal Case'])->assertCreated()->json('data');
        foreach (['consultation', 'documents_required', 'documents_complete', 'university_selection', 'university_application', 'admission_pending'] as $key) {
            $move($c['id'], $key);
        }
        $rejected = $move($c['id'], 'rejected');
        $this->assertSame('closed', $rejected->json('data.status'));
        $appealed = $move($c['id'], 'visa_preparation');
        $this->assertSame('active', $appealed->json('data.status'));
        // Creation + 6 walk moves + refusal + appeal are all audited.
        $this->assertSame(9, Applicant::query()->whereKey($c['id'])->first()->stageHistory()->count());
    }

    public function test_pipeline_is_scoped_to_one_service_and_moves_the_contract_line(): void
    {
        $scholarshipContract = $this->createBuiltInContract('scholarship', ['name' => 'Scholarship Case']);
        $scholarshipLine = ApplicantContract::query()->where('contract_id', $scholarshipContract['id'])->sole();

        // Buying another service creates a second case for another party.
        $offerContract = $this->createBuiltInContract('test_offer', ['name' => 'Test Offer Case']);
        $offerLine = ApplicantContract::query()->where('contract_id', $offerContract['id'])->sole();

        $offers = $this->getJson('/api/applicants-pipeline?service=test_offer')->assertOk()->json('data');
        $this->assertSame('test_offer', $offers['service']);
        $this->assertSame(1, $offers['totals']['all']);
        $this->assertCount(1, $offers['cards']);
        $this->assertSame('line', $offers['cards'][0]['source']);
        $this->assertSame($offerLine->id, $offers['cards'][0]['line_id']);
        $this->assertSame('new', $offers['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($offers['stages'])->firstWhere('key', 'new')['count']);

        // The scholarship board only shows scholarship work, and the card is
        // the contracted line rather than the party.
        $scholarship = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame('scholarship', $scholarship['service']);
        $this->assertSame(1, $scholarship['totals']['all']);
        $this->assertCount(1, $scholarship['cards']);
        $this->assertSame('line', $scholarship['cards'][0]['source']);
        $this->assertSame($scholarshipLine->id, $scholarship['cards'][0]['line_id']);
        $this->assertSame('Scholarship Case', $scholarship['cards'][0]['name']);

        // Moving the line updates this service's board only; the mirrored
        // applicant stage is a compatibility mirror, not the board's source.
        $next = ApplicantStage::query()->where('service', 'test_offer')->where('key', 'documents_required')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$offerLine->id}/change-stage", ['stage_id' => $next->id])->assertOk();

        $moved = $this->getJson('/api/applicants-pipeline?service=test_offer')->assertOk()->json('data');
        $this->assertSame('documents_required', $moved['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($moved['stages'])->firstWhere('key', 'documents_required')['count']);
        $this->assertSame(0, collect($moved['stages'])->firstWhere('key', 'new')['count']);

        // Another service's stage is rejected for this line.
        $academyStage = ApplicantStage::query()->where('service', 'academy')->where('key', 'enrolled')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$offerLine->id}/change-stage", ['stage_id' => $academyStage->id])->assertUnprocessable();
    }

    public function test_line_without_a_stage_is_placed_on_the_service_board_and_can_move(): void
    {
        $contract = $this->createBuiltInContract('academy', ['name' => 'Stageless Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->firstOrFail();
        // Seeded/legacy lines can hold no stage at all; the board must still
        // show them on their service's starting stage and let them move.
        ApplicantContract::query()->whereKey($line->id)->update(['current_stage_id' => null]);

        $board = $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()->json('data');
        $this->assertCount(1, $board['cards']);
        $this->assertSame('line', $board['cards'][0]['source']);
        $this->assertSame('new', $board['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($board['stages'])->firstWhere('key', 'new')['count']);

        $next = ApplicantStage::query()->where('service', 'academy')->where('key', 'enrolled')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $next->id])->assertOk();
        $this->assertDatabaseHas('applicant_contract', ['id' => $line->id, 'current_stage_id' => $next->id]);
    }

    public function test_changing_a_case_service_reseats_it_on_that_service_workflow(): void
    {
        $a = $this->postJson('/api/applicants', ['name' => 'Switching Case'])->assertCreated()->json('data');
        $consultation = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'consultation')->firstOrFail();
        $this->postJson("/api/applicants/{$a['id']}/change-stage", ['stage_id' => $consultation->id])->assertOk();

        $new = ApplicantStage::query()->where('service', 'academy')->where('key', 'new')->firstOrFail();
        $this->putJson("/api/applicants/{$a['id']}", ['service' => 'academy'])
            ->assertOk()
            ->assertJsonPath('data.current_stage.key', 'new');
        $this->assertDatabaseHas('applicants', ['id' => $a['id'], 'current_stage_id' => $new->id, 'service' => 'academy']);

        // Switching the party's own service does not manufacture a service case:
        // the board only gains a card once a line is sold.
        $academy = $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()->json('data');
        $this->assertSame(0, $academy['totals']['all']);
        $this->assertSame([], $academy['cards']);
    }

    public function test_unlined_cases_are_absent_from_every_pipeline_board(): void
    {
        $this->postJson('/api/applicants', ['name' => 'Academy Case', 'service' => 'academy'])->assertCreated();

        $academy = $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()->json('data');
        $this->assertSame(0, $academy['totals']['all']);
        $this->assertSame([], $academy['cards']);

        $scholarship = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(0, $scholarship['totals']['all']);
        $this->assertSame([], $scholarship['cards']);
    }
}
