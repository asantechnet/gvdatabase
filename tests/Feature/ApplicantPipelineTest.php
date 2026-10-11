<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_legacy_unstaged_applicants_are_assigned_to_a_real_new_stage_and_can_move(): void
    {
        $applicant = Applicant::factory()->create(['current_stage_id' => null]);
        $migration = require database_path('migrations/2026_10_08_000003_repair_new_applicant_stage.php');
        $migration->up();
        $migration->up();
        $new = ApplicantStage::where('key', 'new')->whereNull('country_id')->whereNull('university_id')->firstOrFail();
        $this->assertSame($new->id, $applicant->fresh()->current_stage_id);
        $this->getJson('/api/applicants-pipeline')->assertOk()->assertJsonFragment(['key' => 'new', 'count' => 1]);
        $consultation = ApplicantStage::where('key', 'consultation')->firstOrFail();
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

        // Operation board holds both active cases.
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

        $this->postJson('/api/applicants', ['name' => 'Case 1', 'country_id' => $iran->id])->assertCreated();
        $this->postJson('/api/applicants', ['name' => 'Case 2', 'country_id' => $turkey->id])->assertCreated();

        $all = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(2, $all['totals']['all']);
        $this->assertSame(2, $all['totals']['active']);
        $this->assertSame(array_sum(array_column($all['stages'], 'count')), $all['totals']['all']);

        $filtered = $this->getJson("/api/applicants-pipeline?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['totals']['all']);
        $this->assertSame(1, array_sum(array_column($filtered['stages'], 'count')));
        $this->assertNotEmpty($filtered['stages']);
        $this->assertArrayHasKey('key', $filtered['stages'][0]);
        $this->assertArrayHasKey('allowed_next_keys', $filtered['stages'][0]);
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
        $stageId = fn (string $key) => ApplicantStage::query()->where('key', $key)->firstOrFail()->id;
        $move = fn (int $id, string $key) => $this->postJson("/api/applicants/{$id}/change-stage", ['stage_id' => $stageId($key)])->assertOk();

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
}
