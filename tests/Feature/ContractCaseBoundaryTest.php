<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ContractCaseBoundaryTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_test_offer_and_academy_contracts_create_no_case_stage_or_history(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['test_offer', 'academy'] as $service) {
            $created = $this->createBuiltInContract($service);
            $applicant = Applicant::query()->findOrFail($created['applicant_id']);
            $line = ApplicantContract::query()->where('contract_id', $created['id'])->sole();

            $this->assertNull($applicant->current_stage_id, $service);
            $this->assertNull($line->current_stage_id, $service);
            $this->assertSame(0, ApplicantStageHistory::query()->where('applicant_id', $applicant->id)->count(), $service);
            $this->assertSame(0, ApplicantStage::query()->where('service', $service)->where('is_active', true)->count(), $service);
        }
    }

    public function test_stage_api_has_no_case_free_catalogue_or_writes(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/applicant-stages?service=test_offer')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/applicants-pipeline?service=academy')
            ->assertOk()->assertJsonCount(0, 'data.cards');
        $this->postJson('/api/applicant-stages', [
            'service' => 'test_offer', 'key' => 'waiting_list', 'name' => 'Waiting list',
        ])->assertStatus(422)->assertJsonPath('code', 'business.applicant_stage_service_not_supported');
        $this->assertDatabaseMissing('applicant_stages', ['service' => 'test_offer', 'key' => 'waiting_list']);

        $stage = ApplicantStage::query()->where('service', 'test_offer')->firstOrFail();
        $this->deleteJson('/api/applicant-stages/'.$stage->id)
            ->assertStatus(422)->assertJsonPath('code', 'business.applicant_stage_service_not_supported');
    }

    public function test_retirement_migration_clears_stage_references_and_is_idempotent(): void
    {
        $stage = ApplicantStage::query()->where('service', 'test_offer')->firstOrFail();
        DB::table('applicant_stages')->where('id', $stage->id)->update(['is_active' => true]);
        $applicant = Applicant::factory()->create(['service' => 'test_offer']);
        DB::table('applicants')->where('id', $applicant->id)->update(['current_stage_id' => $stage->id]);

        $migration = require database_path('migrations/2026_11_07_000007_retire_test_offer_and_academy_stages.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('applicant_stages', ['id' => $stage->id, 'is_active' => false]);
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'current_stage_id' => null]);
        $this->assertDatabaseHas('applicant_stages', ['id' => $stage->id]);
    }
}
