<?php

namespace Tests\Feature;

use App\Models\ApplicantStage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class ApplicantStageCatalogTest extends AcademyTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_stage_catalogue_is_service_scoped_and_hides_inactive_rows_by_default(): void
    {
        ApplicantStage::query()->where('service', 'scholarship')->where('key', 'documents_required')
            ->firstOrFail()->update(['is_active' => false]);

        $active = $this->getJson('/api/applicant-stages?service=scholarship')->assertOk()->json('data');
        $this->assertNotContains('documents_required', array_column($active, 'key'));
        $this->assertSame([], array_values(array_diff(array_unique(array_column($active, 'service')), ['scholarship'])));

        // The editor asks for everything, including the rows a picker hides.
        $all = $this->getJson('/api/applicant-stages?service=scholarship&all=1')->assertOk()->json('data');
        $this->assertContains('documents_required', array_column($all, 'key'));

        $academy = $this->getJson('/api/applicant-stages?service=academy')->assertOk()->json('data');
        $this->assertContains('enrolled', array_column($academy, 'key'));
        $this->assertNotContains('consultation', array_column($academy, 'key'));
    }

    public function test_only_the_canonical_terminal_set_is_terminal(): void
    {
        $canonical = [
            'scholarship' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
            'private' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
            'test_offer' => ['completed', 'cancelled', 'rejected', 'withdrawn'],
            'academy' => ['completed', 'cancelled', 'withdrawn'],
        ];

        foreach ($canonical as $service => $keys) {
            $rows = collect($this->getJson("/api/applicant-stages?service={$service}&all=1")->assertOk()->json('data'))
                ->keyBy('key');

            $terminal = $rows->filter(fn (array $row) => $row['is_terminal'] === true)->pluck('key')->sort()->values()->all();
            $this->assertSame(collect($keys)->sort()->values()->all(), $terminal, "{$service} terminal set");

            foreach ($keys as $key) {
                $this->assertTrue($rows[$key]['is_active'], "{$service}.{$key} must be active");
                $this->assertTrue($rows[$key]['is_locked'], "{$service}.{$key} must be locked");
            }

            // Seeded non-terminal stages stay editable.
            $this->assertFalse($rows['new']['is_locked'], "{$service}.new must stay editable");
        }
    }

    public function test_test_offer_has_a_rejected_outcome_after_the_existing_outcomes_and_academy_has_none(): void
    {
        $testOffer = collect($this->getJson('/api/applicant-stages?service=test_offer&all=1')->assertOk()->json('data'))
            ->keyBy('key');
        $academy = collect($this->getJson('/api/applicant-stages?service=academy&all=1')->assertOk()->json('data'))
            ->keyBy('key');

        $this->assertTrue($testOffer['rejected']['is_terminal']);
        $this->assertTrue($testOffer['rejected']['is_locked']);
        $this->assertGreaterThan($testOffer['withdrawn']['sort_order'], $testOffer['rejected']['sort_order']);
        $this->assertArrayNotHasKey('rejected', $academy);
    }

    public function test_a_locked_stage_cannot_be_updated_or_deleted(): void
    {
        $stage = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'completed')->firstOrFail();
        $before = $stage->only(['name', 'sort_order', 'is_active', 'is_terminal', 'allowed_next_keys', 'is_locked']);

        $this->putJson('/api/applicant-stages/'.$stage->id, [
            'name' => 'Renamed', 'sort_order' => 1, 'is_active' => false,
            'is_terminal' => false, 'allowed_next_keys' => [],
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.applicant_stage_locked');

        $this->assertSame($before, $stage->fresh()->only(array_keys($before)));

        $this->deleteJson('/api/applicant-stages/'.$stage->id)
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.applicant_stage_locked');

        $this->assertNull($stage->fresh()->deleted_at);
    }

    public function test_a_locked_stage_answers_409_before_validation(): void
    {
        $stage = ApplicantStage::query()->where('service', 'academy')->where('key', 'withdrawn')->firstOrFail();

        // Even an invalid body must not be able to distinguish the row's fields.
        $this->putJson('/api/applicant-stages/'.$stage->id, ['name' => null, 'sort_order' => -10])
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.applicant_stage_locked');
    }

    public function test_is_locked_is_ignored_from_the_request_body(): void
    {
        $created = $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'waiting_list', 'name' => 'Waiting list', 'is_locked' => true,
        ])->assertCreated()->json('data');
        $this->assertFalse($created['is_locked']);

        $this->putJson('/api/applicant-stages/'.$created['id'], ['name' => 'Waiting list (renamed)', 'is_locked' => true])
            ->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.name', 'Waiting list (renamed)');

        // A body cannot unlock a seeded stage either.
        $locked = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'cancelled')->firstOrFail();
        $this->putJson('/api/applicant-stages/'.$locked->id, ['is_locked' => false])
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.applicant_stage_locked');
        $this->assertTrue($locked->fresh()->is_locked);
    }

    public function test_terminal_status_is_system_owned(): void
    {
        $this->postJson('/api/applicant-stages', [
            'service' => 'academy', 'key' => 'graduation', 'name' => 'Graduation', 'is_terminal' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_terminal.0', 'business.applicant_stage_terminal_managed');

        $this->assertDatabaseMissing('applicant_stages', ['service' => 'academy', 'key' => 'graduation']);

        $stage = ApplicantStage::query()->where('service', 'academy')->where('key', 'enrolled')->firstOrFail();
        $this->putJson('/api/applicant-stages/'.$stage->id, ['name' => 'Enrolled', 'is_terminal' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_terminal.0', 'business.applicant_stage_terminal_managed');
        $this->assertFalse($stage->fresh()->is_terminal);

        // The model refuses the same write even when a controller forgets.
        try {
            $stage->is_terminal = true;
            $stage->save();
            $this->fail('The model must refuse a terminal write outside the controller.');
        } catch (ValidationException $exception) {
            $this->assertSame(['business.applicant_stage_terminal_managed'], $exception->errors()['is_terminal']);
        }
        $this->assertFalse($stage->fresh()->is_terminal);
    }

    public function test_a_new_stage_is_never_terminal(): void
    {
        $created = $this->postJson('/api/applicant-stages', [
            'service' => 'academy', 'key' => 'orientation', 'name' => 'Orientation',
        ])->assertCreated()->json('data');

        $this->assertFalse($created['is_terminal']);
        $this->assertFalse($created['is_locked']);
    }

    public function test_a_stage_keeps_its_service_and_a_soft_deleted_key_is_reserved(): void
    {
        $created = $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'waiting_list', 'name' => 'Waiting list', 'sort_order' => 55,
        ])->assertCreated()->json('data');

        // A stage belongs to its service; an update cannot reparent it.
        $this->putJson('/api/applicant-stages/'.$created['id'], ['service' => 'academy', 'name' => 'Waiting list (renamed)'])
            ->assertOk()->assertJsonPath('data.service', 'test_offer')->assertJsonPath('data.name', 'Waiting list (renamed)');

        $this->deleteJson('/api/applicant-stages/'.$created['id'])->assertNoContent();
        $this->assertSoftDeleted('applicant_stages', ['id' => $created['id']]);

        // The composite index still reserves the key, so the replacement gets
        // the next free suffix instead of colliding with the soft-deleted row.
        $replacement = $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'waiting_list', 'name' => 'Waiting list again',
        ])->assertCreated()->json('data');
        $this->assertSame('waiting_list_2', $replacement['key']);
    }

    public function test_duplicate_keys_are_made_unique_inside_a_service_but_shared_across_services(): void
    {
        $first = $this->postJson('/api/applicant-stages', ['service' => 'test_offer', 'key' => 'orientation', 'name' => 'Orientation'])
            ->assertCreated()->json('data');
        $this->assertSame('orientation', $first['key']);

        $second = $this->postJson('/api/applicant-stages', ['service' => 'test_offer', 'key' => 'orientation', 'name' => 'Duplicate'])
            ->assertCreated()->json('data');
        $this->assertSame('orientation_2', $second['key']);

        // The same key is still valid in another service's list.
        $academy = $this->postJson('/api/applicant-stages', ['service' => 'academy', 'key' => 'orientation', 'name' => 'Orientation'])
            ->assertCreated()->json('data');
        $this->assertSame('orientation', $academy['key']);
    }

    public function test_a_missing_key_is_generated_from_the_stage_name(): void
    {
        $created = $this->postJson('/api/applicant-stages', [
            'service' => 'academy', 'name' => 'Evening Orientation',
        ])->assertCreated()->json('data');
        $this->assertSame('evening_orientation', $created['key']);

        $again = $this->postJson('/api/applicant-stages', [
            'service' => 'academy', 'name' => 'Evening Orientation',
        ])->assertCreated()->json('data');
        $this->assertSame('evening_orientation_2', $again['key']);
    }
}
