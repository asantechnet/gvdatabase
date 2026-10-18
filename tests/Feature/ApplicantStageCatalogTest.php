<?php

namespace Tests\Feature;

use App\Models\ApplicantStage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantStageCatalogTest extends TestCase
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
        ApplicantStage::query()->where('service', 'scholarship')->where('key', 'completed')
            ->firstOrFail()->update(['is_active' => false]);

        $active = $this->getJson('/api/applicant-stages?service=scholarship')->assertOk()->json('data');
        $this->assertNotContains('completed', array_column($active, 'key'));
        $this->assertSame([], array_values(array_diff(array_unique(array_column($active, 'service')), ['scholarship'])));

        // The editor asks for everything, including the rows a picker hides.
        $all = $this->getJson('/api/applicant-stages?service=scholarship&all=1')->assertOk()->json('data');
        $this->assertContains('completed', array_column($all, 'key'));

        $academy = $this->getJson('/api/applicant-stages?service=academy')->assertOk()->json('data');
        $this->assertContains('enrolled', array_column($academy, 'key'));
        $this->assertNotContains('consultation', array_column($academy, 'key'));
    }

    public function test_a_stage_keeps_its_service_and_its_key_stays_reserved(): void
    {
        $created = $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'waiting_list', 'name' => 'Waiting list', 'sort_order' => 55,
        ])->assertCreated()->json('data');

        // A stage belongs to its service; an update cannot reparent it.
        $this->putJson('/api/applicant-stages/'.$created['id'], ['service' => 'academy', 'name' => 'Waiting list (renamed)'])
            ->assertOk()->assertJsonPath('data.service', 'test_offer')->assertJsonPath('data.name', 'Waiting list (renamed)');

        $this->deleteJson('/api/applicant-stages/'.$created['id'])->assertNoContent();
        $this->assertSoftDeleted('applicant_stages', ['id' => $created['id']]);

        // The composite index still reserves the key, so the API refuses it
        // instead of failing on the constraint.
        $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'waiting_list', 'name' => 'Waiting list again',
        ])->assertUnprocessable();
    }

    public function test_duplicate_keys_are_rejected_inside_a_service_but_allowed_across_services(): void
    {
        $this->postJson('/api/applicant-stages', ['service' => 'test_offer', 'key' => 'orientation', 'name' => 'Orientation'])->assertCreated();
        $this->postJson('/api/applicant-stages', ['service' => 'test_offer', 'key' => 'orientation', 'name' => 'Duplicate'])->assertUnprocessable();
        $this->postJson('/api/applicant-stages', ['service' => 'academy', 'key' => 'orientation', 'name' => 'Orientation'])->assertCreated();
    }
}
