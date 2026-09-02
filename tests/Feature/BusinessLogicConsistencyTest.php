<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Board;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\PaidTimeOff;
use App\Models\Project;
use App\Models\RecurringEvent;
use App\Models\Role;
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
            'status' => 'unpaid-off-day',
            'logged_at' => '00:00:00',
        ]);

        $this->assertSame('unpaid-off-day', $attendance->fresh()->status);
    }

    public function test_commission_uses_the_recorded_base_amount(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $employee = Employee::factory()->create(['employment_type' => 'commission']);
        $employee->commissionServices()->attach($service->id, ['percentage' => 10]);
        $invoice = Invoice::factory()->create([
            'currency_id' => $currency->id,
            'base_currency_id' => $currency->id,
            'amount' => 100,
            'subtotal' => 100,
            'base_amount' => 100,
        ]);
        $invoice->services()->attach($service->id, [
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
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'base_currency_id' => $base->id,
            'amount' => 200,
            'subtotal' => 200,
            'rate' => 2,
            'base_amount' => 100,
        ]);
        $income = Income::create([
            'customer_id' => $customer->id,
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
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        CustomerService::create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'deadline' => today()->addMonth()->toDateString(),
        ]);

        $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'class_type' => 'Standard',
            'services' => [['id' => $service->id, 'quantity' => 1]],
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
            'name' => 'Customer Agreement',
            'contract_type_id' => $type->id,
            'cover_text' => 'Cover',
            'content' => '<p>Current template text</p>',
        ]);
        $customer = Customer::factory()->create();
        $contract = Contract::query()->create([
            'template_id' => $template->id,
            'contract_type_id' => $type->id,
            'customer_id' => $customer->id,
            'name' => 'Original name',
            'type' => $type->name,
            'content' => '<p>Signed snapshot text</p>',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'counterparty' => $customer->name,
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

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
