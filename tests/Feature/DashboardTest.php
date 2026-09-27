<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Board;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-13 10:00:00');
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_returns_real_financial_attendance_and_task_metrics(): void
    {
        $user = User::factory()->create(['roles' => ['super_admin'], 'acc_status' => 'active']);
        $currency = Currency::factory()->create([
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'is_base' => true,
            'rate' => 1,
        ]);
        $board = Board::factory()->create(['title' => 'Delivery']);
        Task::factory()->create([
            'board_id' => $board->id,
            'title' => 'Release applicant portal',
            'progress' => 80,
            'due_date' => '2026-08-20',
        ]);
        $completed = Task::factory()->create([
            'board_id' => $board->id,
            'progress' => 100,
        ]);
        DB::table('tasks')->where('id', $completed->id)->update(['updated_at' => '2026-08-10 12:00:00']);

        $this->insertIncome($currency->id, '2026-08-08', 1200, 'RCP-TEST-1');
        $this->insertIncome($currency->id, '2026-07-08', 1000, 'RCP-TEST-2');
        $this->insertExpense($currency->id, '2026-08-09', 200);

        $presentEmployee = Employee::factory()->create();
        $absentEmployee = Employee::factory()->create();
        Attendance::create([
            'employee_id' => $presentEmployee->id,
            'date' => '2026-08-12',
            'status' => 'present',
            'logged_at' => '09:00:00',
        ]);
        Attendance::create([
            'employee_id' => $absentEmployee->id,
            'date' => '2026-08-12',
            'status' => 'absent',
            'logged_at' => '09:00:00',
        ]);

        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.capabilities.finance', true)
            ->assertJsonPath('data.capabilities.attendance', true)
            ->assertJsonPath('data.currency.code', 'USD')
            ->assertJsonPath('data.summary.monthly_income', 1200)
            ->assertJsonPath('data.summary.previous_month_income', 1000)
            ->assertJsonPath('data.summary.active_tasks', 1)
            ->assertJsonPath('data.summary.nearing_completion_tasks', 1)
            ->assertJsonPath('data.summary.completed_tasks_this_month', 1)
            ->assertJsonPath('data.summary.attendance_rate', 50)
            ->assertJsonPath('data.tertiary_chart.current_value', 1000)
            ->assertJsonPath('data.active_tasks.0.title', 'Release applicant portal')
            ->assertJsonPath('data.active_tasks.0.board', 'Delivery')
            ->assertJsonCount(7, 'data.weekly_activity.labels')
            ->assertJsonCount(7, 'data.secondary_chart.rates');
    }

    public function test_staff_dashboard_only_contains_assigned_tasks_and_hides_restricted_aggregates(): void
    {
        $staff = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $coworker = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $assignedBoard = Board::factory()->create(['title' => 'Assigned Board']);
        $hiddenBoard = Board::factory()->create(['title' => 'Hidden Board']);
        $assignedBoard->users()->attach($staff->id);
        $hiddenBoard->users()->attach($coworker->id);

        $assignedTask = Task::factory()->create([
            'board_id' => $assignedBoard->id,
            'title' => 'Visible task',
            'progress' => 40,
        ]);
        $hiddenTask = Task::factory()->create([
            'board_id' => $hiddenBoard->id,
            'title' => 'Hidden task',
            'progress' => 20,
        ]);
        $assignedTask->users()->attach($staff->id);
        $hiddenTask->users()->attach($coworker->id);

        Sanctum::actingAs($staff, ['access']);

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.capabilities.finance', false)
            ->assertJsonPath('data.capabilities.attendance', false)
            ->assertJsonPath('data.summary.monthly_income', null)
            ->assertJsonPath('data.summary.attendance_rate', null)
            ->assertJsonPath('data.summary.active_tasks', 1)
            ->assertJsonCount(1, 'data.active_tasks')
            ->assertJsonPath('data.active_tasks.0.title', 'Visible task');
    }

    private function insertIncome(int $currencyId, string $date, float $total, string $receiptNumber): void
    {
        DB::table('incomes')->insert([
            'receipt_number' => $receiptNumber,
            'currency_id' => $currencyId,
            'type' => 'Payment',
            'description' => 'Dashboard test income',
            'amount' => $total,
            'quantity' => 1,
            'currency' => 'USD',
            'base_currency_id' => $currencyId,
            'cnv_curr_shrt' => 'USD',
            'base_curr_shrt' => 'USD',
            'rate' => 1,
            'base_amount' => $total,
            'date' => $date,
            'created_at' => $date.' 10:00:00',
            'updated_at' => $date.' 10:00:00',
        ]);
    }

    private function insertExpense(int $currencyId, string $date, float $total): void
    {
        DB::table('expenses')->insert([
            'currency_id' => $currencyId,
            'type' => 'Operations',
            'description' => 'Dashboard test expense',
            'amount' => $total,
            'quantity' => 1,
            'currency' => 'USD',
            'base_currency_id' => $currencyId,
            'cnv_curr_shrt' => 'USD',
            'base_curr_shrt' => 'USD',
            'rate' => 1,
            'base_amount' => $total,
            'date' => $date,
            'created_at' => $date.' 10:00:00',
            'updated_at' => $date.' 10:00:00',
        ]);
    }
}
