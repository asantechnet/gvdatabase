<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Attendance;
use App\Models\Board;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Income;
use App\Models\LeaveRequest;
use App\Models\RecurringEvent;
use App\Models\Role;
use App\Models\Salary;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurringEvents\RecurringEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessLogicConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_absent_is_a_persistable_attendance_status(): void
    {
        $attendance = Attendance::create([
            'employee_id' => Employee::factory()->create()->id,
            'date' => '2026-08-31',
            'status' => 'absent',
            'logged_at' => '00:00:00',
        ]);

        $this->assertSame('absent', $attendance->fresh()->status);
    }

    public function test_inactivating_a_user_revokes_existing_tokens(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->createToken('existing-session');
        Sanctum::actingAs($admin, ['access']);

        $this->putJson("/api/users/{$user->id}/status", ['acc_status' => 'inactive'])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_editing_a_linked_payment_preserves_its_rate_and_recalculates_its_base_amount(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $base = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $currency = Currency::factory()->create(['is_base' => false, 'rate' => 2]);
        $applicant = Applicant::factory()->create();
        $line = $this->contractedService($applicant, ['fee' => 200, 'currency_id' => $currency->id, 'currency_rate' => 2]);
        $income = Income::create([
            'applicant_id' => $applicant->id,
            'applicant_contract_id' => $line->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $base->id,
            'type' => 'Service',
            'description' => 'Initial payment',
            'amount' => 100,
            'quantity' => 1,
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $base->code,
            'rate' => 2,
            'date' => '2026-08-31',
        ]);
        $currency->update(['rate' => 4]);

        $this->putJson("/api/incomes/{$income->id}", [
            'description' => 'Corrected description',
            'amount' => 120,
            'revaluation_reason' => 'Corrected the receipt amount',
        ])->assertOk();

        $income->refresh();
        $this->assertSame('2.00000000', $income->rate);
        $this->assertSame('60.000000', $income->base_amount);
    }

    public function test_the_final_generated_recurring_occurrence_remains_executable(): void
    {
        $user = $this->superAdmin();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $event = RecurringEvent::create([
            'owner_id' => $user->id,
            'name' => 'Final rent payment',
            'event_type' => 'expense',
            'status' => 'active',
            'frequency' => 'monthly',
            'interval' => 1,
            'start_date' => today(),
            'next_occurrence_date' => today(),
            'end_date' => today(),
            'payload' => [
                'currency_id' => $currency->id,
                'type' => 'Rent',
                'description' => 'Office rent',
                'amount' => 100,
            ],
        ]);
        $service = app(RecurringEventService::class);

        $this->assertTrue($service->notifyDueEvent($event));
        $event->refresh();
        $this->assertSame('completed', $event->status);

        $occurrence = $event->occurrences()->firstOrFail();
        $executed = $service->execute($occurrence, $user);

        $this->assertSame('completed', $executed->status);
        $this->assertNotNull($executed->result_id);
    }

    public function test_a_contract_payment_cannot_exceed_the_line_balance(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $applicant = Applicant::factory()->create();
        $line = $this->contractedService($applicant, ['fee' => 100, 'currency_id' => $currency->id, 'currency_rate' => 1]);

        $this->postJson('/api/incomes', [
            'applicant_contract_id' => $line->id,
            'type' => 'Service',
            'description' => 'Deposit',
            'amount' => 250,
            'quantity' => 1,
            'currency_id' => $currency->id,
            'date' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'business.contract_payment_exceeds_balance');

        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_payroll_rejects_over_deduction_and_recreation_of_a_deleted_period(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $employee = Employee::factory()->create();
        $payload = [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 100,
            'date' => '2026-08-31',
            'deductions' => [['description' => 'Invalid deduction', 'amount' => 101]],
        ];

        $this->postJson('/api/salaries', $payload)->assertUnprocessable()
            ->assertJsonPath('errors.deductions.0', 'business.deductions_exceed_payable');

        $salary = Salary::factory()->create([
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salary_month' => '2026-08',
        ]);
        $salary->delete();
        $payload['deductions'] = [];

        $this->postJson('/api/salaries', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('salary_month');
    }

    public function test_leave_cannot_be_recorded_on_the_weekly_day_off(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $employee = Employee::factory()->create(['join_date' => '2026-09-01']);

        $this->postJson("/api/employees/{$employee->id}/leave-requests", [
            'date' => '2026-09-11',
            'duration_days' => 1,
            'reason' => 'Invalid Friday leave',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'business.leave_date_is_weekly_day_off');

        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_granting_leave_does_not_overwrite_worked_attendance(): void
    {
        $admin = $this->superAdmin();
        Sanctum::actingAs($admin, ['access']);
        $employee = Employee::factory()->create();
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-31',
            'status' => 'present',
            'logged_at' => '08:00:00',
            'check_in_time' => '08:00:00',
            'checked_in_at' => '2026-08-31 08:00:00',
        ]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-31',
            'year' => 2026,
            'reason' => 'Personal leave',
            'requested_by' => $admin->id,
            'is_granted' => false,
        ]);

        $this->patchJson('/api/leave-requests/grant', ['ids' => [$leave->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'business.leave_request_conflicts_with_attendance');

        $this->assertFalse($leave->fresh()->is_granted);
        $this->assertSame('present', $attendance->fresh()->status);
        $this->assertSame('08:00:00', $attendance->fresh()->check_in_time);
    }

    public function test_a_contract_can_be_partially_updated_without_requiring_or_rerendering_its_template(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $type = ContractType::query()->where('slug', 'other')->firstOrFail();
        $template = ContractTemplate::query()->create([
            'name' => 'Applicant Agreement',
            'contract_type_id' => $type->id,
            'cover_text' => 'Cover',
            'content' => '<p>Current template text</p>',
        ]);
        $applicant = Applicant::factory()->create();
        $contract = Contract::query()->create([
            'template_id' => $template->id,
            'contract_type_id' => $type->id,
            'applicant_id' => $applicant->id,
            'name' => 'Original name',
            'type' => $type->name,
            'content' => '<p>Signed snapshot text</p>',
            'status' => 'draft',
            'start_date' => '2026-01-01',
            'counterparty' => $applicant->name,
        ]);

        $this->putJson("/api/contracts/{$contract->id}", ['name' => 'Corrected name'])
            ->assertOk()
            ->assertJsonPath('data.contract', 'Corrected name');

        $this->assertSame('<p>Signed snapshot text</p>', $contract->fresh()->content);
    }

    public function test_task_creation_rolls_back_when_a_related_service_is_invalid(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $board = Board::factory()->create();
        $missingServiceId = 1000;

        $this->postJson('/api/tasks', [
            'board_id' => $board->id,
            'title' => 'Invalid service task',
            'service_ids' => [$missingServiceId],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid service task']);
    }

    public function test_task_dependencies_cannot_form_a_cycle(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $board = Board::factory()->create();
        $first = Task::factory()->create(['board_id' => $board->id]);
        $second = Task::factory()->create(['board_id' => $board->id]);
        $first->dependencies()->attach($second->id);

        $this->putJson("/api/tasks/{$second->id}", ['dependencies' => [$first->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.dependencies.0', 'business.task_dependency_cycle');

        $this->assertFalse($second->dependencies()->whereKey($first->id)->exists());
    }

    private function contractedService(Applicant $applicant, array $line = [], string $status = 'active'): ApplicantContract
    {
        $contract = Contract::create([
            'contract_type_id' => ContractType::query()->where('slug', 'customer')->value('id'),
            'applicant_id' => $applicant->id,
            'name' => 'Scholarship agreement', 'type' => 'Customer', 'status' => $status,
            'start_date' => '2026-09-01', 'counterparty' => $applicant->name,
            'content' => '', 'is_custom' => true,
        ]);

        return $contract->applicantContracts()->create(['service' => 'scholarship', 'applicant_id' => $applicant->id] + $line);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
