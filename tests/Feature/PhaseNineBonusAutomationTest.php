<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AutomationRun;
use App\Models\Board;
use App\Models\BonusRuleSet;
use App\Models\CaseWin;
use App\Models\Currency;
use App\Models\DailyAnalytics;
use App\Models\Employee;
use App\Models\MonthlyAnalytics;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\WorthyEmployee;
use App\Services\AutomationRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseNineBonusAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create([
            'navigation_clr' => '#111111', 'cards_clr' => '#ffffff', 'footer_clr' => '#111111',
            'off_day_allowance_days_per_year' => 20, 'website_title' => 'Test', 'website_address' => 'https://example.test',
            'timezone' => 'Asia/Kabul', 'phone' => '123', 'physical_address' => 'Kabul',
            'short_company_name' => 'Test', 'original_company_name' => 'Test Company',
            'office_open_time' => '08:00', 'office_close_time' => '16:00', 'default_break_minutes' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_task_credit_and_case_win_produce_one_explainable_approval_required_nomination(): void
    {
        Carbon::setTestNow('2026-08-13 08:00:00 UTC');
        $reviewer = $this->superAdmin();
        $worker = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $worker->id]);
        $applicant = Applicant::factory()->create(['responsible_employee_id' => $employee->id]);
        $currency = Currency::factory()->create(['code' => 'AFN', 'name' => 'Afghani', 'symbol' => '؋', 'is_base' => true, 'rate' => 1]);
        $task = Task::factory()->create([
            'board_id' => Board::factory(), 'progress' => 99, 'priority' => 'high',
            'due_date' => '2026-07-25', 'updated_at' => '2026-07-01 00:00:00',
        ]);
        $task->users()->attach($worker->id);
        $task->update(['progress' => 100]);
        $task->update(['completed_at' => '2026-07-20 06:00:00']);
        CaseWin::create(['applicant_id' => $applicant->id, 'employee_id' => $employee->id, 'won_at' => '2026-07-21 06:00:00', 'stage_key' => 'visa_approved']);

        Sanctum::actingAs($reviewer, ['access']);
        $this->patchJson("/api/tasks/{$task->id}/bonus-review", [
            'quality_score' => 4, 'contributions' => [(string) $worker->id => 100],
        ])->assertOk()->assertJsonPath('data.managerQualityScore', 4);

        $this->artisan('bonuses:calculate', ['--month' => '2026-07'])->assertSuccessful();
        $this->artisan('bonuses:calculate', ['--month' => '2026-07'])->assertSuccessful();

        $this->assertSame(1, WorthyEmployee::count());
        $nomination = WorthyEmployee::firstOrFail();
        $this->assertSame($employee->id, $nomination->employee_id);
        $this->assertFalse($nomination->is_granted);
        $this->assertSame('task-case-bonus:2026-07:'.$employee->id, $nomination->calculation_key);
        $this->assertEqualsWithDelta(2.3333, $nomination->calculation_score, .0001);
        $this->assertEqualsWithDelta(113.33, $nomination->suggested_amount, .001);
        $this->assertSame('Performance', $nomination->selected_type);
        $this->assertSame(1, $nomination->calculation_inputs['cases_won']);
        $this->assertNotEmpty($nomination->calculation_inputs['tasks'][0]['completed_at']);
        $this->assertSame(1, Notification::where('notification_key', 'bonus.review.2026-07.'.$employee->id)->count());
        $this->assertSame(1, AutomationRun::where(['job' => 'bonus_calculation', 'period_key' => '2026-07', 'status' => 'completed'])->count());
        $this->assertSame($currency->id, $nomination->currency_id);
    }

    public function test_rule_updates_are_versioned_and_automation_apis_are_permission_protected(): void
    {
        $unauthorized = User::factory()->create();
        Sanctum::actingAs($unauthorized, ['access']);
        $this->getJson('/api/automation/runs')->assertForbidden();
        $this->getJson('/api/automation/analytics')->assertForbidden();

        $admin = $this->superAdmin();
        Sanctum::actingAs($admin, ['access']);
        $rules = BonusRuleSet::where('is_active', true)->firstOrFail()->rules;
        $rules['amount_per_completed_task'] = 25;
        $this->putJson('/api/automation/bonus-rules', $rules)
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.rules.amount_per_completed_task', 25);
        $this->assertSame(2, BonusRuleSet::count());
        $this->assertSame(1, BonusRuleSet::where('is_active', true)->count());
    }

    public function test_completed_task_credit_alone_produces_a_nomination(): void
    {
        Carbon::setTestNow('2026-08-13 08:00:00 UTC');
        $this->superAdmin();
        Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);

        $taskUser = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $taskUser->id]);
        $task = Task::factory()->create(['board_id' => Board::factory(), 'progress' => 100, 'completed_at' => '2026-07-10 06:00:00']);
        $task->users()->attach($taskUser->id, ['contribution_percent' => 100]);

        $this->artisan('bonuses:calculate', ['--month' => '2026-07'])->assertSuccessful();

        $nomination = WorthyEmployee::firstOrFail();
        $this->assertSame($employee->id, $nomination->employee_id);
        $this->assertSame(0, $nomination->calculation_inputs['cases_won']);
        $this->assertEqualsWithDelta(1, $nomination->calculation_inputs['task_credit'], .0001);
        $this->assertEqualsWithDelta(10, $nomination->suggested_amount, .001);
        $this->assertFalse($nomination->is_granted);
    }

    public function test_bonus_preview_explains_the_projection_without_writing_anything(): void
    {
        Carbon::setTestNow('2026-08-13 08:00:00 UTC');
        $reviewer = $this->superAdmin();
        $worker = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $worker->id]);
        Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'code' => 'AFN', 'symbol' => '؋']);
        $task = Task::factory()->create(['board_id' => Board::factory(), 'progress' => 100, 'completed_at' => '2026-07-10 06:00:00']);
        $task->users()->attach($worker->id, ['contribution_percent' => 100]);

        Sanctum::actingAs($reviewer, ['access']);
        $response = $this->getJson("/api/tasks/{$task->id}/bonus-preview")->assertOk();

        $this->assertSame('2026-07', $response->json('data.month'));
        $this->assertSame($employee->id, $response->json('data.assignees.0.employeeId'));
        $this->assertEqualsWithDelta(1, $response->json('data.assignees.0.thisTaskCredit'), .0001);
        $this->assertEqualsWithDelta(1, $response->json('data.assignees.0.monthCredit'), .0001);
        $this->assertSame(0, $response->json('data.assignees.0.casesWon'));
        $this->assertTrue($response->json('data.assignees.0.eligible'));
        $this->assertEqualsWithDelta(10, $response->json('data.assignees.0.projectedAmount'), .001);
        $this->assertNull($response->json('data.assignees.0.nomination'));

        $this->assertDatabaseCount('worthy_employees', 0);
        $this->assertDatabaseCount('automation_runs', 0);

        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson("/api/tasks/{$task->id}/bonus-preview")->assertForbidden();
    }

    public function test_analytics_backfill_is_idempotent_and_failure_history_is_retryable(): void
    {
        Task::factory()->create([
            'board_id' => Board::factory(), 'progress' => 100,
            'created_at' => '2026-07-10 03:30:00', 'completed_at' => '2026-07-10 04:00:00',
        ]);
        Employee::factory()->create(['join_date' => '2026-07-10']);

        $this->artisan('analytics:sync', ['--from' => '2026-07-10', '--to' => '2026-07-10'])->assertSuccessful();
        $this->artisan('analytics:sync', ['--from' => '2026-07-10', '--to' => '2026-07-10'])->assertSuccessful();

        $daily = DailyAnalytics::whereDate('date', '2026-07-10')->firstOrFail();
        $this->assertSame(1, $daily->tasks_created);
        $this->assertSame(1, $daily->tasks_completed);
        $this->assertSame(1, $daily->employees_onboarded);
        $this->assertSame(1, DailyAnalytics::count());
        $this->assertSame(1, MonthlyAnalytics::where(['year' => 2026, 'month' => 7])->count());

        try {
            app(AutomationRunService::class)->execute('test_job', '2026-07', [], false, fn () => throw new \RuntimeException('boom'));
            $this->fail('The failing callback should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }
        $failed = AutomationRun::where(['job' => 'test_job', 'period_key' => '2026-07'])->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('boom', $failed->error_message);
        $retry = app(AutomationRunService::class)->execute('test_job', '2026-07', [], false, fn () => ['ok' => true]);
        $this->assertTrue($retry['ok']);
        $this->assertSame(2, $failed->fresh()->attempt);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));

        return $user;
    }
}
