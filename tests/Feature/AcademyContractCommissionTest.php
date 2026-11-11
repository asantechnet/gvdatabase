<?php

namespace Tests\Feature;

use App\Models\ApplicantStage;
use App\Models\CommissionAccrual;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;
use Tests\Concerns\CreatesBuiltInContracts;

class AcademyContractCommissionTest extends AcademyTestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::query()->update(['is_base' => false]);
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
    }

    private function commissionEmployee(string $service, float $percentage): Employee
    {
        $employee = Employee::factory()->create(['is_commission_based' => true]);
        $employee->serviceCommissions()->create(['service' => $service, 'percentage' => $percentage]);

        return $employee;
    }

    private function soldBy(Contract $contract, Employee $employee): Contract
    {
        $contract->forceFill(['employee_id' => $employee->id])->save();

        return $contract->fresh();
    }

    public function test_an_academy_contract_does_not_earn_counselor_commission(): void
    {
        $employee = $this->commissionEmployee('academy', 10);
        $contract = $this->soldBy(
            Contract::findOrFail($this->createBuiltInContract('academy')['id']),
            $employee,
        );
        $applicant = $contract->applicant;

        $this->assertFalse($applicant->isCaseCompleted());
        $this->assertNull($applicant->current_stage_id);

        app(CommissionService::class)->syncForContract($contract);

        $this->assertSame(0, CommissionAccrual::query()->where('contract_id', $contract->id)->count());
    }

    public function test_a_test_offer_contract_earns_only_after_its_case_completes(): void
    {
        $employee = $this->commissionEmployee('test_offer', 10);
        $contract = $this->soldBy(
            Contract::findOrFail($this->createBuiltInContract('test_offer')['id']),
            $employee,
        );
        $applicant = $contract->applicant;

        $this->assertNotNull($applicant->current_stage_id);
        $this->assertFalse($applicant->isCaseCompleted());

        app(CommissionService::class)->syncForContract($contract);
        $this->assertSame(0, CommissionAccrual::query()->where('contract_id', $contract->id)->count());

        $completed = ApplicantStage::query()
            ->where('service', 'test_offer')
            ->where('key', ApplicantStage::COMPLETED_KEY)
            ->firstOrFail();
        DB::table('applicants')->where('id', $applicant->id)->update([
            'current_stage_id' => $completed->id,
            'status' => 'closed',
        ]);

        app(CommissionService::class)->syncForApplicant($applicant->fresh());

        $accrual = CommissionAccrual::query()
            ->where('contract_id', $contract->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertSame('pending', $accrual->status);
        $this->assertSame('test_offer', $accrual->service);
        $this->assertSame(100.0, (float) $accrual->eligible_base_amount);
        $this->assertSame(10.0, (float) $accrual->commission_base_amount);
    }
}
