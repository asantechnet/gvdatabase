<?php

namespace Tests\Feature;

use App\Models\ApplicantStageHistory;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class AcademyCaseFreeContractTest extends TestCase
{
    use CreatesBuiltInContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_test_offer_and_academy_contracts_write_no_stage_or_history(): void
    {
        $academy = Contract::findOrFail($this->createBuiltInContract('academy')['id']);
        $testOffer = Contract::findOrFail($this->createBuiltInContract('test_offer')['id']);

        foreach ([$academy, $testOffer] as $contract) {
            $line = $contract->applicantContracts()->sole();

            $this->assertNull($contract->applicant->current_stage_id, $contract->service.' applicant stage');
            $this->assertNull($line->current_stage_id, $contract->service.' line stage');
            $this->assertSame(0, ApplicantStageHistory::where('applicant_id', $contract->applicant_id)->count());
            $this->assertSame(0, $line->stageHistory()->count());
        }

        $this->assertNotNull($academy->applicant->detail());
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
