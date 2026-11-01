<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

/**
 * Commission is earned by the case, not by the agreement: a service line pays
 * out once its applicant reaches the terminal `completed` stage.
 */
class CommissionLifecycleTest extends TestCase
{
    use CreatesBuiltInContracts;
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    /** A commission-based employee with a rate for the given service. */
    private function commissionEmployee(string $service, float $percentage): Employee
    {
        $employee = Employee::factory()->create(['is_commission_based' => true]);
        $employee->serviceCommissions()->create(['service' => $service, 'percentage' => $percentage]);

        return $employee;
    }

    /**
     * A live contract for a case that is still in the pipeline. The commission
     * fields are set directly because only the named employees may be paid.
     */
    private function liveContract(array $employeeIds, string $service = 'scholarship'): Contract
    {
        Currency::query()->where('is_base', true)->update(['is_base' => false]);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $responsible = $employeeIds[0] ?? Employee::factory()->create()->id;
        $data = $this->createBuiltInContract($service, [
            'fee' => 100, 'discount' => 0, 'currency_id' => $currency->id, 'currency_rate' => 1,
            'responsible_employee_id' => $responsible,
        ]);
        $contract = Contract::findOrFail($data['id']);
        $contract->forceFill([
            'employee_id' => $employeeIds[0] ?? null,
            'sold_by_employee_id' => $employeeIds[1] ?? null,
        ])->save();

        return $contract->fresh();
    }

    /**
     * Walk a case up its own workflow — through the real change-stage endpoint,
     * so the transition rules are exercised — until it reaches the target stage.
     */
    private function advanceCase(Applicant $applicant, string $target): Applicant
    {
        // Leaving the first stage needs a labeled document. The workflow under
        // test is the transition and its commission effect, not the upload.
        if ($applicant->attachments()->doesntExist()) {
            $this->seedApplicantDocuments($applicant);
        }

        for ($step = 0; $step < 30; $step++) {
            $current = $applicant->fresh()->currentStage;
            if ($current?->key === $target) {
                return $applicant->fresh();
            }

            $this->assertNotNull($current, 'the case has no current stage');
            $forward = collect($current->allowed_next_keys ?? [])
                ->map(fn (string $key) => ApplicantStage::query()->where('key', $key)->where('is_active', true)->first())
                ->filter()
                ->first(fn (ApplicantStage $stage) => $stage->key === $target
                    || ! in_array($stage->key, ApplicantStage::EXIT_KEYS, true));

            $this->assertNotNull($forward, "no forward transition from {$current->key}");

            $this->postJson("/api/applicants/{$applicant->id}/change-stage", [
                'stage_id' => $forward->id,
                'note' => "Advance to {$forward->key}",
            ])->assertOk();
        }

        $this->fail("the case never reached {$target}");
    }

    public function test_completing_a_case_accrues_commission_for_commission_based_employees(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 50);
        $contract = $this->liveContract([$employee->id]);

        // A live, issued contract for a case still in the pipeline earns nothing.
        $this->assertSame(0, CommissionAccrual::query()->count());

        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $accrual = CommissionAccrual::where('contract_id', $contract->id)->where('employee_id', $employee->id)->first();
        $this->assertNotNull($accrual);
        $this->assertSame('pending', $accrual->status);
        $this->assertSame('scholarship', $accrual->service);
        $this->assertSame(100.0, (float) $accrual->eligible_base_amount);
        $this->assertSame(50.0, (float) $accrual->commission_base_amount);
        $this->assertSame(50.0, (float) $accrual->percentage);
        // The accrual is dated by the completion, so settlement periods line up.
        $this->assertSame(now()->toDateString(), $accrual->earned_at->toDateString());
    }

    public function test_a_completed_contract_alone_does_not_accrue_commission(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 50);
        $contract = $this->liveContract([$employee->id]);

        // The agreement finishing is not the trigger; the case finishing is.
        $contract->update(['status' => 'completed']);

        $this->assertSame(0, CommissionAccrual::query()->count());

        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $this->assertSame(1, CommissionAccrual::where('employee_id', $employee->id)->count());
    }

    public function test_closing_a_case_at_an_exit_stage_does_not_accrue_commission(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 50);
        $contract = $this->liveContract([$employee->id]);

        // Reach the last open stage, then close the case instead of completing it.
        $applicant = $this->advanceCase($contract->applicant, 'visa_approved');
        $cancelled = ApplicantStage::query()->where('key', 'cancelled')->firstOrFail();
        $this->postJson("/api/applicants/{$applicant->id}/change-stage", [
            'stage_id' => $cancelled->id,
            'note' => 'Family withdrew the application',
        ])->assertOk();

        $this->assertSame('closed', $applicant->fresh()->status);
        $this->assertSame(0, CommissionAccrual::query()->count());
    }

    public function test_completing_a_case_without_a_base_currency_still_completes_the_case(): void
    {
        $this->actingSuperAdmin();
        $approved = ApplicantStage::query()->where('key', 'visa_approved')->firstOrFail();
        $completed = ApplicantStage::query()->where('key', ApplicantStage::COMPLETED_KEY)->firstOrFail();
        $applicant = Applicant::create([
            'name' => 'Unpriced Case',
            'responsible_employee_id' => Employee::factory()->create()->id,
            'current_stage_id' => $approved->id,
            'status' => 'active',
        ]);
        Currency::query()->update(['is_base' => false]);

        // The accrual is skipped, but the workflow itself must still succeed:
        // an office that has not configured a base currency can close cases.
        $this->postJson("/api/applicants/{$applicant->id}/change-stage", [
            'stage_id' => $completed->id,
            'note' => 'Case finished',
        ])->assertOk();

        $this->assertSame('closed', $applicant->fresh()->status);
        $this->assertSame(0, CommissionAccrual::query()->count());
    }

    public function test_non_commission_employees_and_employees_without_a_rate_do_not_accrue(): void
    {
        $this->actingSuperAdmin();
        $salaried = Employee::factory()->create(['is_commission_based' => false]);
        $noRate = Employee::factory()->create(['is_commission_based' => true]);

        $contract = $this->liveContract([$salaried->id, $noRate->id]);
        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $this->assertSame(0, CommissionAccrual::where('contract_id', $contract->id)->count());
    }

    public function test_a_cancelled_contract_stops_earning_even_on_a_completed_case(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 50);
        $contract = $this->liveContract([$employee->id]);
        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $this->assertSame(1, CommissionAccrual::where('contract_id', $contract->id)->count());

        // Cancelling the agreement voids what it had earned but not settled.
        $this->putJson("/api/contracts/{$contract->id}/cancel")->assertOk();

        $this->assertSame('void', CommissionAccrual::where('contract_id', $contract->id)->firstOrFail()->status);
    }

    public function test_reconciliation_voids_accruals_whose_case_left_the_completed_stage(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 50);
        $contract = $this->liveContract([$employee->id]);
        $completed = $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $this->assertSame('pending', CommissionAccrual::where('contract_id', $contract->id)->firstOrFail()->status);

        // Simulate a correction made outside the workflow (no history row, so no
        // stage hook): the reconcile action has to bring the ledger back in step.
        $consultation = ApplicantStage::query()->where('key', 'consultation')->firstOrFail();
        $completed->forceFill(['current_stage_id' => $consultation->id])->save();

        $this->postJson('/api/commissions/sync')->assertOk()
            ->assertJsonPath('data.cases', 0)
            ->assertJsonPath('data.voided', 1);

        $this->assertSame('void', CommissionAccrual::where('contract_id', $contract->id)->firstOrFail()->status);
    }

    public function test_syncing_the_same_completed_case_twice_does_not_duplicate_accruals(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 50);
        $contract = $this->liveContract([$employee->id]);
        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $this->postJson('/api/commissions/sync')->assertOk()
            ->assertJsonPath('data.cases', 1)
            ->assertJsonPath('data.voided', 0);

        $this->assertSame(1, CommissionAccrual::where('contract_id', $contract->id)->count());
        $accrual = CommissionAccrual::where('contract_id', $contract->id)->firstOrFail();
        $this->assertSame('pending', $accrual->status);
        $this->assertSame(50.0, (float) $accrual->commission_base_amount);
    }

    public function test_sync_does_not_resurrect_a_paid_accrual(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 40);
        $contract = $this->liveContract([$employee->id]);
        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $accrual = CommissionAccrual::where('employee_id', $employee->id)->firstOrFail();
        $today = now()->toDateString();
        $settlementId = $this->postJson('/api/commission-settlements', [
            'employee_id' => $employee->id,
            'base_currency_id' => $accrual->base_currency_id,
            'period_start' => $today,
            'period_end' => $today,
            'accrual_ids' => [$accrual->id],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/commission-settlements/{$settlementId}/pay", ['payment_date' => $today])
            ->assertOk();

        $paid = CommissionAccrual::findOrFail($accrual->id);
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->commission_settlement_id);

        $this->postJson('/api/commissions/sync')->assertOk();

        $after = CommissionAccrual::findOrFail($accrual->id);
        $this->assertSame('paid', $after->status);
        $this->assertSame($paid->commission_settlement_id, $after->commission_settlement_id);
        $this->assertSame((float) $paid->commission_base_amount, (float) $after->commission_base_amount);
    }

    public function test_settlement_approval_and_payment_book_an_expense(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 40);
        $contract = $this->liveContract([$employee->id]);
        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $accrual = CommissionAccrual::where('employee_id', $employee->id)->firstOrFail();
        $today = now()->toDateString();

        $settlementId = $this->postJson('/api/commission-settlements', [
            'employee_id' => $employee->id,
            'base_currency_id' => $accrual->base_currency_id,
            'period_start' => $today,
            'period_end' => $today,
            'accrual_ids' => [$accrual->id],
        ])->assertCreated()->json('data.id');

        // A super administrator creates the settlement already approved, which
        // mirrors the trusted internal review path.
        $this->postJson("/api/commission-settlements/{$settlementId}/pay", ['payment_date' => $today])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $settlement = CommissionSettlement::findOrFail($settlementId);
        $this->assertNotNull($settlement->expense_id);
        $this->assertSame(40.0, (float) Expense::findOrFail($settlement->expense_id)->amount);
        $this->assertSame('paid', CommissionAccrual::findOrFail($accrual->id)->status);
    }

    public function test_commission_workspace_endpoints_serialize_rows(): void
    {
        $this->actingSuperAdmin();
        $employee = $this->commissionEmployee('scholarship', 30);
        $contract = $this->liveContract([$employee->id]);
        $this->advanceCase($contract->applicant, ApplicantStage::COMPLETED_KEY);

        $this->getJson('/api/commissions/accruals')->assertOk()
            ->assertJsonPath('data.0.service', 'scholarship')
            ->assertJsonPath('data.0.employee_id', $employee->id);
        $this->getJson('/api/commissions/summary')->assertOk()
            ->assertJsonPath('data.0.status', 'pending');
        $this->getJson('/api/commission-settlements')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_employee_form_persists_commission_flag_and_per_service_rates(): void
    {
        $this->actingSuperAdmin();

        $id = $this->postJson('/api/employees', [
            'name' => 'Commission Person', 'father_name' => 'Father', 'contact' => '0700000000',
            'id_number' => 'C-'.uniqid(), 'education_lvl' => 'Bachelor', 'salary' => 0,
            'addr' => 'Somewhere', 'join_date' => now()->toDateString(),
            'create_user' => false,
            'position_id' => Position::factory()->create(['status' => 'active'])->id,
            'is_commission_based' => true,
            'service_commissions' => [
                ['service' => 'scholarship', 'percentage' => 10],
                ['service' => 'academy', 'percentage' => 5.5],
                ['service' => 'scholarship', 'percentage' => 10],
            ],
        ])->assertCreated()->json('data.id');

        $employee = Employee::findOrFail($id);
        $this->assertTrue($employee->is_commission_based);
        $this->assertSame(2, $employee->serviceCommissions()->count());
        $this->assertSame(5.5, (float) $employee->serviceCommissions()->where('service', 'academy')->value('percentage'));
    }
}
