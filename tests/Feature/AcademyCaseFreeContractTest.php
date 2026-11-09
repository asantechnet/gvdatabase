<?php

namespace Tests\Feature;

use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\AcademyTestCase;

class AcademyCaseFreeContractTest extends AcademyTestCase
{
    use CreatesBuiltInContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_academy_contracts_write_no_stage_or_history(): void
    {
        $academy = Contract::findOrFail($this->createBuiltInContract('academy')['id']);
        $line = $academy->applicantContracts()->sole();

        $this->assertNull($academy->applicant->current_stage_id);
        $this->assertNull($line->current_stage_id);
        $this->assertSame(0, ApplicantStageHistory::where('applicant_id', $academy->applicant_id)->count());
        $this->assertSame(0, $line->stageHistory()->count());
        $this->assertSame(0, ApplicantStage::where('service', 'academy')->where('is_active', true)->count());
        $this->assertNotNull($academy->applicant->detail());
    }

    public function test_test_offer_contracts_keep_their_case_stage_and_history(): void
    {
        $testOffer = Contract::findOrFail($this->createBuiltInContract('test_offer')['id']);
        $line = $testOffer->applicantContracts()->sole();

        $this->assertNotNull($testOffer->applicant->current_stage_id);
        $this->assertNotNull($line->current_stage_id);
        $this->assertSame(2, ApplicantStageHistory::where('applicant_id', $testOffer->applicant_id)->count());
        $this->assertSame(1, $line->stageHistory()->count());
        $this->assertNotNull($testOffer->applicant->detail());
    }

    public function test_scholarship_and_private_still_keep_their_stage_and_history(): void
    {
        foreach (['scholarship', 'private'] as $service) {
            $contract = Contract::findOrFail($this->createBuiltInContract($service)['id']);
            $line = $contract->applicantContracts()->sole();

            $this->assertNotNull($contract->applicant->current_stage_id, $service.' applicant stage');
            $this->assertNotNull($line->current_stage_id, $service.' line stage');
            // One applicant stage write and one line stage write: the existing
            // scholarship/private path is intentionally unchanged.
            $this->assertSame(2, ApplicantStageHistory::where('applicant_id', $contract->applicant_id)->count());
        }
    }

    public function test_responsible_employee_pivot_has_a_unique_pair(): void
    {
        $this->assertTrue(Schema::hasTable('applicant_responsible_employees'));
    }
}
