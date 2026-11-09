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
use Tests\AcademyTestCase;

class ContractCaseBoundaryTest extends AcademyTestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_test_offer_contracts_create_a_case_while_academy_stays_case_free(): void
    {
        $this->actingAsSuperAdmin();

        $testOffer = $this->createBuiltInContract('test_offer');
        $testApplicant = Applicant::query()->findOrFail($testOffer['applicant_id']);
        $testLine = ApplicantContract::query()->where('contract_id', $testOffer['id'])->sole();

        $this->assertNotNull($testApplicant->current_stage_id);
        $this->assertNotNull($testLine->current_stage_id);
        // One applicant stage write and one line stage write.
        $this->assertSame(2, ApplicantStageHistory::query()->where('applicant_id', $testApplicant->id)->count());
        $this->assertGreaterThan(0, ApplicantStage::query()->where('service', 'test_offer')->where('is_active', true)->count());

        $academy = $this->createBuiltInContract('academy');
        $academyApplicant = Applicant::query()->findOrFail($academy['applicant_id']);
        $academyLine = ApplicantContract::query()->where('contract_id', $academy['id'])->sole();

        $this->assertNull($academyApplicant->current_stage_id);
        $this->assertNull($academyLine->current_stage_id);
        $this->assertSame(0, ApplicantStageHistory::query()->where('applicant_id', $academyApplicant->id)->count());
        $this->assertSame(0, ApplicantStage::query()->where('service', 'academy')->where('is_active', true)->count());
    }

    public function test_stage_api_offers_test_offer_and_hides_academy(): void
    {
        $this->actingAsSuperAdmin();

        $stages = $this->getJson('/api/applicant-stages?service=test_offer')
            ->assertOk()->json('data');
        $this->assertNotEmpty($stages);
        $this->assertSame(['test_offer'], array_values(array_unique(array_column($stages, 'service'))));

        $this->getJson('/api/applicants-pipeline?service=academy')
            ->assertOk()->assertJsonCount(0, 'data.cards');

        $this->postJson('/api/applicant-stages', [
            'service' => 'academy', 'key' => 'waiting_list', 'name' => 'Waiting list',
        ])->assertStatus(422)->assertJsonPath('code', 'business.applicant_stage_service_not_supported');
        $this->assertDatabaseMissing('applicant_stages', ['service' => 'academy', 'key' => 'waiting_list']);

        $stage = ApplicantStage::query()->where('service', 'academy')->firstOrFail();
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
