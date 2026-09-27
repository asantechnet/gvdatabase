<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Board;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Applicant;
use App\Models\ContractService;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\PaidTimeOff;
use App\Models\Project;
use App\Models\RecurringEvent;
use App\Models\Role;
use App\Models\Salary;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurringEvents\RecurringEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessLogicConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_leave_is_a_persistable_attendance_status(): void
    {
        $attendance = Attendance::create([
            'employee_id' => Employee::factory()->create()->id,
            'date' => '2026-08-31',
            'status' => 'unpaid-leave',
            'logged_at' => '00:00:00',
        ]);

        $this->assertSame('unpaid-leave', $attendance->fresh()->status);
    }

    public function test_commission_uses_the_recorded_base_amount(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $applicant = Applicant::factory()->create();
        $employee = Employee::factory()->create(['employment_type' => 'commission']);
        $employee->commissionServices()->attach($service->id, ['percentage' => 10]);
        $applicantService = $this->contractedService($applicant, $service, ['fee' => 100, 'currency_id' => $currency->id]);
        $invoice = Invoice::factory()->create([
            'applicant_id' => $applicant->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $currency->id,
            'amount' => 100,
            'subtotal' => 100,
            'base_amount' => 100,
        ]);
        $invoice->services()->attach($service->id, [
            'contract_service_id' => $applicantService->id,
            'employee_id' => $employee->id,
            'snapshot_commission_percentage' => 10,
            'snapshot_name' => $service->type,
            'snapshot_service_fee' => 100,
            'snapshot_service_currency_code' => $currency->code,
            'snapshot_service_currency_symbol' => $currency->symbol,
            'snapshot_service_rate' => 1,
            'snapshot_cnv_curr_shrt' => $currency->code,
            'snapshot_base_curr_shrt' => $currency->code,
            'snapshot_rate' => 1,
            'snapshot_invoice_rate' => 1,
            'snapshot_converted_fee' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);
        Income::create([
            'invoice_id' => $invoice->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $currency->id,
            'type' => 'Service',
            'description' => 'Partial payment',
            'amount' => 50,
            'quantity' => 1,
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $currency->code,
            'rate' => 1,
            'invoice_amount' => 50,
            'date' => '2026-08-31',
        ]);

        $this->assertSame(5.0, $employee->commissionEarnedBase());
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

    public function test_editing_a_linked_payment_preserves_its_rate_and_recalculates_its_invoice_amount(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $base = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $currency = Currency::factory()->create(['is_base' => false, 'rate' => 2]);
        $applicant = Applicant::factory()->create();
        $invoice = Invoice::factory()->create([
            'applicant_id' => $applicant->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $base->id,
            'amount' => 200,
            'subtotal' => 200,
            'rate' => 2,
            'base_amount' => 100,
        ]);
        $income = Income::create([
            'applicant_id' => $applicant->id,
            'invoice_id' => $invoice->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $base->id,
            'type' => 'Service',
            'description' => 'Initial payment',
            'amount' => 100,
            'quantity' => 1,
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $base->code,
            'rate' => 2,
            'invoice_amount' => 100,
            'date' => '2026-08-31',
        ]);
        $currency->update(['rate' => 4]);

        $this->putJson("/api/incomes/{$income->id}", [
            'description' => 'Corrected description',
            'amount' => 120,
        ])->assertOk();

        $income->refresh();
        $this->assertSame('2.00000000', $income->rate);
        $this->assertSame('60.000000', $income->base_amount);
        $this->assertSame('120.000000', $income->invoice_amount);
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

    public function test_an_invoice_discount_cannot_exceed_its_total(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $applicant = Applicant::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $applicantService = $this->contractedService($applicant, $service);

        $this->postJson('/api/invoices', [
            'applicant_id' => $applicant->id,
            'currency_id' => $currency->id,
            'services' => [['contract_service_id' => $applicantService->id, 'quantity' => 1]],
            'tax_percent' => 0,
            'discount' => 101,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.discount.0', 'business.invoice_discount_exceeds_total');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_an_invoice_with_a_payment_cannot_be_voided(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $invoice = Invoice::factory()->create([
            'currency_id' => $currency->id,
            'base_currency_id' => $currency->id,
        ]);
        Income::create([
            'invoice_id' => $invoice->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $currency->id,
            'type' => 'Service',
            'description' => 'Deposit',
            'amount' => 10,
            'quantity' => 1,
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $currency->code,
            'rate' => 1,
            'invoice_amount' => 10,
            'date' => '2026-08-31',
        ]);

        $this->putJson("/api/invoices/{$invoice->id}/void", ['reason' => 'Entered twice'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice.0', 'business.invoice_with_payments_cannot_be_voided');

        $this->assertSame('Issued', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->voided_at);
    }

    public function test_reading_a_legacy_invoice_does_not_mutate_its_document_number(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $invoice = Invoice::factory()->create();
        Invoice::withoutEvents(fn () => $invoice->forceFill(['invoice_number' => null])->saveQuietly());

        $this->getJson("/api/invoices/{$invoice->id}")->assertOk();

        $this->assertNull($invoice->fresh()->invoice_number);
    }

    public function test_a_service_line_on_a_non_active_contract_cannot_be_added_to_a_new_invoice(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $applicant = Applicant::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $assignment = $this->contractedService($applicant, $service, [], 'terminated');

        $this->postJson('/api/invoices', [
            'applicant_id' => $applicant->id,
            'currency_id' => $currency->id,
            'services' => [['contract_service_id' => $assignment->id, 'quantity' => 1]],
            'tax_percent' => 0,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.services.0', 'business.invoice_service_not_assigned');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_payments_must_match_currency_and_cannot_predate_issue(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $base = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $other = Currency::factory()->create(['is_base' => false, 'rate' => 2]);
        $invoice = Invoice::factory()->create([
            'currency_id' => $base->id,
            'base_currency_id' => $base->id,
            'issue_date' => '2026-08-10',
            'due_date' => '2026-09-10',
        ]);
        $payload = [
            'invoice_id' => $invoice->id,
            'type' => 'Service',
            'description' => 'Deposit',
            'amount' => 10,
            'quantity' => 1,
            'currency_id' => $other->id,
            'date' => '2026-08-10',
        ];

        $this->postJson('/api/incomes', $payload)->assertUnprocessable()
            ->assertJsonPath('errors.currency_id.0', 'business.invoice_payment_currency_mismatch');

        $payload['currency_id'] = $base->id;
        $payload['date'] = '2026-08-09';
        $this->postJson('/api/incomes', $payload)->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'business.invoice_payment_before_issue');

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

        $this->postJson("/api/employees/{$employee->id}/leaves", [
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
        $leave = PaidTimeOff::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-31',
            'year' => 2026,
            'reason' => 'Personal leave',
            'requested_by' => $admin->id,
            'is_granted' => false,
        ]);

        $this->patchJson('/api/paid-time-offs/grant', ['ids' => [$leave->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'business.paid_time_off_conflicts_with_attendance');

        $this->assertFalse($leave->fresh()->is_granted);
        $this->assertSame('present', $attendance->fresh()->status);
        $this->assertSame('08:00:00', $attendance->fresh()->check_in_time);
    }

    public function test_a_contract_can_be_partially_updated_without_requiring_or_rerendering_its_template(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
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
        $admin = $this->superAdmin();
        Sanctum::actingAs($admin, ['access']);
        $project = Project::query()->create([
            'name' => 'ERP rollout',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $board = Board::factory()->create(['project_id' => $project->id]);
        $service = Service::factory()->create();

        $this->postJson('/api/tasks', [
            'board_id' => $board->id,
            'title' => 'Invalid service task',
            'service_ids' => [$service->id],
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

    private function contractedService(Applicant $applicant, Service $service, array $line = [], string $status = 'active'): ContractService
    {
        $contract = Contract::create([
            'contract_type_id' => ContractType::query()->where('slug', 'customer')->value('id'),
            'applicant_id' => $applicant->id,
            'name' => $service->type, 'type' => 'Customer', 'status' => $status,
            'start_date' => '2026-09-01', 'counterparty' => $applicant->name,
            'content' => '', 'is_custom' => true,
        ]);

        return $contract->contractServices()->create(['service_id' => $service->id] + $line);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
