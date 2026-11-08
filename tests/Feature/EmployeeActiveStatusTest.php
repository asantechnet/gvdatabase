<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Employees carry an active/inactive state and GET responses only ever send
 * active employees, except where a management surface opts in explicitly.
 */
class EmployeeActiveStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    /** @return array<int> */
    private function listedIds(TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('id')->all();
    }

    public function test_employee_directory_sends_only_active_employees_by_default(): void
    {
        // The lowest-id employee is the primary record and is always hidden
        // from listings, so create one first to keep the assertions honest.
        Employee::factory()->create();
        $active = Employee::factory()->create(['status' => Employee::STATUS_ACTIVE]);
        $inactive = Employee::factory()->create(['status' => Employee::STATUS_INACTIVE]);

        $response = $this->getJson('/api/employees')->assertOk();

        $this->assertContains($active->id, $this->listedIds($response));
        $this->assertNotContains($inactive->id, $this->listedIds($response));

        foreach ($response->json('data') as $row) {
            $this->assertSame(Employee::STATUS_ACTIVE, $row['status']);
        }
    }

    public function test_management_status_filter_opts_into_inactive_rows(): void
    {
        Employee::factory()->create();
        $active = Employee::factory()->create(['status' => Employee::STATUS_ACTIVE]);
        $inactive = Employee::factory()->create(['status' => Employee::STATUS_INACTIVE]);

        $inactiveOnly = $this->getJson('/api/employees?status=inactive')->assertOk();
        $this->assertContains($inactive->id, $this->listedIds($inactiveOnly));
        $this->assertNotContains($active->id, $this->listedIds($inactiveOnly));

        $all = $this->getJson('/api/employees?status=all')->assertOk();
        $this->assertContains($active->id, $this->listedIds($all));
        $this->assertContains($inactive->id, $this->listedIds($all));

        $this->getJson('/api/employees?status=archived')->assertUnprocessable();
    }

    public function test_status_is_written_through_store_and_update(): void
    {
        // Primary (lowest-id) employee is always hidden from listings; create
        // one first so the assertions below exercise the status filter only.
        Employee::factory()->create();
        $position = Position::factory()->create();

        $createdId = $this->postJson('/api/employees', [
            'name' => 'Inactive New Hire',
            'father_name' => 'Example Father',
            'contact' => '0700000001',
            'id_number' => 'STATUS-001',
            'education_lvl' => 'Bachelor',
            'position_id' => $position->id,
            'salary' => 1000,
            'addr' => 'Kabul, Afghanistan',
            'join_date' => '2026-09-02',
            'create_user' => false,
            'status' => Employee::STATUS_INACTIVE,
        ])->assertCreated()
            ->assertJsonPath('data.status', Employee::STATUS_INACTIVE)
            ->json('data.id');

        // Hidden from the default directory, reachable through the explicit
        // management filter and through `show` so it can be reactivated.
        $this->assertNotContains($createdId, $this->listedIds($this->getJson('/api/employees')->assertOk()));
        $this->assertContains($createdId, $this->listedIds($this->getJson('/api/employees?status=inactive')->assertOk()));
        $this->getJson("/api/employees/{$createdId}")->assertOk()
            ->assertJsonPath('data.status', Employee::STATUS_INACTIVE);

        $this->putJson("/api/employees/{$createdId}", ['status' => Employee::STATUS_ACTIVE])
            ->assertOk()
            ->assertJsonPath('data.status', Employee::STATUS_ACTIVE);

        $this->assertContains($createdId, $this->listedIds($this->getJson('/api/employees')->assertOk()));

        $this->putJson("/api/employees/{$createdId}", ['status' => 'retired'])->assertUnprocessable();
    }

    public function test_educational_performance_report_excludes_inactive_employees(): void
    {
        Employee::factory()->create();
        $active = Employee::factory()->create(['status' => Employee::STATUS_ACTIVE]);
        $inactive = Employee::factory()->create(['status' => Employee::STATUS_INACTIVE]);

        $rows = collect($this->getJson('/api/educational-reports/employee-performance')->assertOk()->json('data.rows'))->pluck('employee');

        $this->assertContains($active->name, $rows);
        $this->assertNotContains($inactive->name, $rows);
    }

    public function test_task_assignee_picker_excludes_deactivated_employees(): void
    {
        Employee::factory()->create();
        $activeUser = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $activeUser->id, 'status' => Employee::STATUS_ACTIVE]);
        $inactiveUser = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $inactiveUser->id, 'status' => Employee::STATUS_INACTIVE]);

        $ids = collect($this->getJson('/api/task-assignees')->assertOk()->json('data'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($activeUser->id, $ids);
        $this->assertNotContains($inactiveUser->id, $ids);
    }

    public function test_assignment_options_never_send_inactive_employees(): void
    {
        $counselorPosition = Position::firstOrCreate(['system_key' => 'counselor'], ['title' => 'Counselor', 'status' => 'active']);
        Employee::factory()->create();
        $active = Employee::factory()->create(['position_id' => $counselorPosition->id, 'status' => Employee::STATUS_ACTIVE]);
        $inactive = Employee::factory()->create(['position_id' => $counselorPosition->id, 'status' => Employee::STATUS_INACTIVE]);

        $ids = $this->listedIds($this->getJson('/api/employees/assignment-options?specialty=counselor')->assertOk());

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_unmarked_attendance_staff_excludes_inactive_employees(): void
    {
        Employee::factory()->create();
        $active = Employee::factory()->create(['status' => Employee::STATUS_ACTIVE]);
        $inactive = Employee::factory()->create(['status' => Employee::STATUS_INACTIVE]);

        // 2026-09-01 is a past, non-Friday date accepted by the endpoint.
        $ids = $this->listedIds($this->getJson('/api/attendances/unmarked-staff?date=2026-09-01')->assertOk());

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }
}
