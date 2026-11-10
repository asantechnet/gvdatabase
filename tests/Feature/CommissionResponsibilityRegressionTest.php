<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\CommissionAccrual;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;
use Tests\Concerns\CreatesBuiltInContracts;

class CommissionResponsibilityRegressionTest extends AcademyTestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    public function test_academy_accrual_is_not_voided_by_a_case_transition(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        $base = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $seller = Employee::factory()->create();
        $created = $this->createBuiltInContract('academy');
        $contract = Contract::query()->findOrFail($created['id']);
        $line = ApplicantContract::query()->where('contract_id', $contract->id)->sole();
        $accrual = CommissionAccrual::create([
            'contract_id' => $contract->id, 'applicant_contract_id' => $line->id,
            'employee_id' => $seller->id, 'service' => 'academy', 'base_currency_id' => $base->id,
            'entry_type' => 'earned', 'earned_at' => now()->toDateString(),
            'source_base_amount' => 500, 'eligible_base_amount' => 500, 'percentage' => 10,
            'commission_base_amount' => 50, 'status' => 'earned',
        ]);

        app(CommissionService::class)->voidForApplicant(Applicant::query()->findOrFail($created['applicant_id']));

        $this->assertSame('earned', $accrual->fresh()->status);
    }

    public function test_responsible_employees_earn_commission_when_commission_based(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $responsible = Employee::factory()->create([
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
            'is_commission_based' => true,
        ]);
        $responsible->serviceCommissions()->create([
            'service' => 'scholarship',
            'percentage' => 10,
        ]);
        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_ids' => [$responsible->id],
        ]);
        $contract = Contract::query()->findOrFail($created['id']);
        $line = ApplicantContract::query()->where('contract_id', $contract->id)->sole();
        $line->update(['sold_by_employee_id' => null]);
        $contract->update(['employee_id' => null, 'sold_by_employee_id' => null]);
        $completed = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'completed')->firstOrFail();
        DB::table('applicants')->where('id', $created['applicant_id'])->update([
            'current_stage_id' => $completed->id, 'status' => 'closed',
        ]);

        app(CommissionService::class)->syncForApplicant(Applicant::query()->findOrFail($created['applicant_id']));

        $accrual = CommissionAccrual::query()->where('employee_id', $responsible->id)->sole();
        $this->assertSame('pending', $accrual->status);
        $this->assertSame('scholarship', $accrual->service);
        $this->assertSame(10.0, (float) $accrual->percentage);
        $this->assertSame(10.0, (float) $accrual->commission_base_amount);
    }

    public function test_responsible_employee_who_is_also_the_seller_earns_one_accrual(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $employee = Employee::factory()->create([
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
            'is_commission_based' => true,
        ]);
        $employee->serviceCommissions()->create([
            'service' => 'scholarship',
            'percentage' => 10,
        ]);
        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_ids' => [$employee->id],
        ]);
        $contract = Contract::query()->findOrFail($created['id']);
        $line = ApplicantContract::query()->where('contract_id', $contract->id)->sole();
        $contract->update(['employee_id' => $employee->id, 'sold_by_employee_id' => $employee->id]);
        $line->update(['sold_by_employee_id' => $employee->id]);
        $completed = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'completed')->firstOrFail();
        DB::table('applicants')->where('id', $created['applicant_id'])->update([
            'current_stage_id' => $completed->id, 'status' => 'closed',
        ]);

        app(CommissionService::class)->syncForApplicant(Applicant::query()->findOrFail($created['applicant_id']));

        $accruals = CommissionAccrual::query()
            ->where('contract_id', $contract->id)
            ->where('employee_id', $employee->id)
            ->get();
        $this->assertCount(1, $accruals);
        $this->assertSame(10.0, (float) $accruals->first()->commission_base_amount);
    }

    public function test_non_commission_based_responsible_employees_do_not_earn(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $responsible = Employee::factory()->create([
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
            'is_commission_based' => false,
        ]);
        $responsible->serviceCommissions()->create([
            'service' => 'scholarship',
            'percentage' => 10,
        ]);
        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_ids' => [$responsible->id],
        ]);
        $contract = Contract::query()->findOrFail($created['id']);
        $line = ApplicantContract::query()->where('contract_id', $contract->id)->sole();
        $line->update(['sold_by_employee_id' => null]);
        $contract->update(['employee_id' => null, 'sold_by_employee_id' => null]);
        $completed = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'completed')->firstOrFail();
        DB::table('applicants')->where('id', $created['applicant_id'])->update([
            'current_stage_id' => $completed->id, 'status' => 'closed',
        ]);

        app(CommissionService::class)->syncForApplicant(Applicant::query()->findOrFail($created['applicant_id']));

        $this->assertSame(0, CommissionAccrual::query()->where('employee_id', $responsible->id)->count());
    }
}
