<?php

namespace Tests\Feature;

use App\Models\AcademyEnrollment;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Country;
use App\Models\Task;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\AcademyTestCase;

class ApplicantStageContextTest extends AcademyTestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    private function line(string $service, array $data = []): ApplicantContract
    {
        $contract = $this->createBuiltInContract($service, $data);

        return ApplicantContract::query()->where('contract_id', $contract['id'])->sole();
    }

    private function stageId(string $service, string $key): int
    {
        return (int) ApplicantStage::query()->where('service', $service)->where('key', $key)->firstOrFail()->id;
    }

    public function test_a_case_read_returns_only_stages_that_apply_to_that_line(): void
    {
        $line = $this->line('scholarship');
        ApplicantStage::create([
            'service' => 'private', 'key' => 'private_only', 'name' => 'Private only', 'is_active' => true,
        ]);

        $rows = $this->getJson("/api/applicant-stages?line_id={$line->id}")->assertOk()->json('data');
        $keys = array_column($rows, 'key');

        $this->assertContains('new', $keys);
        $this->assertNotContains('private_only', $keys);
        $this->assertSame(['scholarship'], array_values(array_unique(array_column($rows, 'service'))));
        // A stage read never carries the case's personal or financial payload.
        $this->assertArrayNotHasKey('applicant', $rows[0]);

        $applicantRows = $this->getJson("/api/applicant-stages?applicant_id={$line->applicant_id}")->assertOk()->json('data');
        $this->assertSame(['scholarship'], array_values(array_unique(array_column($applicantRows, 'service'))));
    }

    public function test_a_case_free_service_refuses_stage_writes_without_history(): void
    {
        $line = $this->line('academy');
        $stage = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'new')->firstOrFail();

        $historyBefore = ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count();
        $tasksBefore = Task::query()->where('applicant_contract_id', $line->id)->count();

        // Academy is case-free, so it has no stage catalogue to read.
        $this->getJson("/api/applicant-stages?line_id={$line->id}")->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $stage->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.applicant_stage_service_not_supported');

        // No stage, history or delivery task row changed.
        $this->assertSame($historyBefore, ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count());
        $this->assertSame($tasksBefore, Task::query()->where('applicant_contract_id', $line->id)->count());
        $this->assertNull($line->fresh()->current_stage_id);
    }

    public function test_an_academy_case_has_no_stage_catalogue_or_writes(): void
    {
        $line = $this->line('academy');
        $stage = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'new')->firstOrFail();

        $this->getJson("/api/applicant-stages?line_id={$line->id}")->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $stage->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.applicant_stage_service_not_supported');

        $this->assertNull($line->fresh()->current_stage_id);
        $this->assertSame(0, ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count());
    }

    public function test_an_academy_case_needs_no_country_test_or_university(): void
    {
        $line = $this->line('academy');
        $enrollment = AcademyEnrollment::query()->where('applicant_contract_id', $line->id)->firstOrFail();

        // An academy case is a direct enrollment: a term exists without any
        // country, test or university scope attached to it.
        $this->assertNotNull($enrollment->started_on);
        $this->assertNotNull($enrollment->ends_on);
        $this->assertNull($line->current_stage_id);
        $this->assertSame('active', $enrollment->status);
    }

    public function test_an_admission_stage_scoped_to_another_country_is_refused(): void
    {
        $line = $this->line('scholarship');
        $other = Country::create(['name' => 'Other country', 'code' => 'OTH']);
        $stage = ApplicantStage::create([
            'service' => 'scholarship', 'key' => 'other_country_only', 'name' => 'Other country only',
            'country_id' => $other->id, 'is_active' => true,
        ]);

        $new = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'new')->firstOrFail();
        $new->update(['allowed_next_keys' => [...$new->allowed_next_keys, 'other_country_only']]);

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $stage->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.stage_id.0', 'business.applicant_stage_context_mismatch');
    }

    public function test_the_model_refuses_a_cross_test_stage_without_the_controller(): void
    {
        $line = $this->line('test_offer');
        $toefl = Test::create(['name' => 'TOEFL', 'is_active' => true]);
        $stage = ApplicantStage::create([
            'service' => 'test_offer', 'key' => 'toefl_only', 'name' => 'TOEFL only',
            'test_id' => $toefl->id, 'is_active' => true,
        ]);
        $before = (int) $line->current_stage_id;

        try {
            $line->update(['current_stage_id' => $stage->id]);
            $this->fail('The line model must refuse a stage from another test.');
        } catch (ValidationException $exception) {
            $this->assertSame(['business.applicant_stage_context_mismatch'], $exception->errors()['stage_id']);
        }
        $this->assertSame($before, (int) $line->fresh()->current_stage_id);

        try {
            $line->applicant->update(['current_stage_id' => $stage->id]);
            $this->fail('The applicant mirror must refuse a stage from another test.');
        } catch (ValidationException $exception) {
            $this->assertSame(['business.applicant_stage_context_mismatch'], $exception->errors()['stage_id']);
        }
        $this->assertSame($before, (int) $line->applicant->fresh()->current_stage_id);
    }
}
