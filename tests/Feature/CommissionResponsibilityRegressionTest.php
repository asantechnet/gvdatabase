<?php

namespace Tests\Feature;

use App\Models\CommissionAccrual;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class CommissionResponsibilityRegressionTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    public function test_retired_case_free_accrual_is_not_voided_or_assigned_to_responsible_employees(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        $base = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $seller = Employee::factory()->create();
        $created = $this->createBuiltInContract('test_offer');
        $contract = Contract::query()->findOrFail($created['id']);
        $line = ApplicantContract::query()->where('contract_id', $contract->id)->sole();
        $accrual = CommissionAccrual::create([
            'contract_id' => $contract->id, 'applicant_contract_id' => $line->id,
            'employee_id' => $seller->id, 'service' => 'test_offer', 'base_currency_id' => $base->id,
            'entry_type' => 'earned', 'earned_at' => now()->toDateString(),
            'source_base_amount' => 500, 'eligible_base_amount' => 500, 'percentage' => 10,
            'commission_base_amount' => 50, 'status' => 'earned',
        ]);

        app(CommissionService::class)->voidForApplicant(Applicant::query()->findOrFail($created['applicant_id']));

        $this->assertSame('earned', $accrual->fresh()->status);
    }

    public function test_responsible_employees_are_never_inserted_as_commission_recipients(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $responsible = Employee::factory()->create([
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
        ]);
        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_ids' => [$responsible->id],
        ]);
        $contract = Contract::query()->findOrFail($created['id']);
        $line = ApplicantContract::query()->where('contract_id', $contract->id)->sole();
        $line->update(['sold_by_employee_id' => null]);
        $contract->update(['employee_id' => null, 'sold_by_employee_id' => null]);
        $completed = \App\Models\ApplicantStage::query()->where('service', 'scholarship')->where('key', 'completed')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('applicants')->where('id', $created['applicant_id'])->update([
            'current_stage_id' => $completed->id, 'status' => 'closed',
        ]);

        app(CommissionService::class)->syncForApplicant(Applicant::query()->findOrFail($created['applicant_id']));

        $this->assertSame(0, CommissionAccrual::query()->where('employee_id', $responsible->id)->count());
    }
}
