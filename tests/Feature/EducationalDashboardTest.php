<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class EducationalDashboardTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Employee::factory()->create(); // The primary system employee is excluded from staff reports.
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_overview_reports_pipeline_sales_staff_and_attention(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Overview Case', 'country_id' => $iran->id, 'responsible_employee_id' => $employee->id, 'fee' => 500]);
        $a = ['id' => $contract['applicant_id']];

        $board = Board::factory()->create();
        Task::create(['board_id' => $board->id, 'applicant_id' => $a['id'], 'title' => 'Overdue case work', 'priority' => 'high', 'progress' => 0, 'due_date' => now()->subDay()->toDateString(), 'order' => 0]);

        // The case has sat untouched for 20 days, so it counts as stuck.
        DB::table('applicant_stage_histories')->where('applicant_id', $a['id'])->update(['changed_at' => now()->subDays(20)]);

        $data = $this->getJson('/api/educational-dashboard')->assertOk()->json('data');

        // Pipeline totals reconcile with the column counts.
        $this->assertSame($data['pipeline']['totals']['all'], array_sum(array_column($data['pipeline']['stages'], 'count')));
        $this->assertSame(1, $data['pipeline']['totals']['active']);

        // Sales and staff attribution.
        $this->assertSame(1, $data['sales']['scholarships']['sold']);
        $staff = collect($data['staff'])->firstWhere('id', $employee->id);
        $this->assertSame(1, $staff['active_applicants']);

        // Attention surfaces the overdue task and the idle case.
        $this->assertNotEmpty($data['attention']['overdue_tasks']);
        $this->assertNotEmpty($data['attention']['stuck_applicants']);
        $this->assertSame($iran->name, $data['countries'][0]['name']);
    }

    public function test_overview_respects_filters_and_permissions(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $this->postJson('/api/applicants', ['name' => 'Case 1', 'country_id' => $iran->id])->assertCreated();
        $this->postJson('/api/applicants', ['name' => 'Case 2', 'country_id' => $turkey->id])->assertCreated();

        $filtered = $this->getJson("/api/educational-dashboard?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['pipeline']['totals']['all']);

        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/educational-dashboard')->assertForbidden();
    }
}
