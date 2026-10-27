<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\MonthlyAttendanceSummary;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\MonthlyAttendanceProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrPayrollWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function setting(array $overrides = []): Setting
    {
        return Setting::create(array_merge([
            'navigation_clr' => '#ffffff', 'cards_clr' => '#ffffff', 'footer_clr' => '#ffffff',
            'off_day_allowance_days_per_year' => 0, 'website_title' => 'Test',
            'website_address' => 'https://example.test', 'timezone' => 'Asia/Kabul',
            'phone' => '123', 'physical_address' => 'Test address',
            'short_company_name' => 'Test', 'original_company_name' => 'Test Company',
        ], $overrides));
    }

    public function test_a_new_employee_inherits_the_company_off_day_allowance(): void
    {
        $this->actingAsSuperAdmin();
        $this->setting(['off_day_allowance_days_per_year' => 18]);
        $position = Position::factory()->create();

        $base = [
            'father_name' => 'Abdul Rahimi',
            'contact' => '0700000000',
            'education_lvl' => 'Bachelor',
            'position_id' => $position->id,
            'salary' => 30000,
            'addr' => 'Street 4, Kabul',
            'join_date' => '2026-01-01',
            'create_user' => false,
            'deductions' => [],
        ];

        $this->postJson('/api/employees', [...$base, 'name' => 'Karim Rahimi', 'id_number' => 'EMP-2001'])
            ->assertCreated();
        $this->assertDatabaseHas('employees', ['id_number' => 'EMP-2001', 'off_day_allowance_days' => 18]);

        $this->postJson('/api/employees', [
            ...$base, 'name' => 'Sara Rahimi', 'id_number' => 'EMP-2002', 'off_day_allowance_days' => 5,
        ])->assertCreated();
        $this->assertDatabaseHas('employees', ['id_number' => 'EMP-2002', 'off_day_allowance_days' => 5]);
    }

    public function test_the_missing_attendance_policy_materializes_only_elapsed_workdays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
        $this->setting(['missing_attendance_status' => 'off_day']);
        $employee = Employee::factory()->create(['join_date' => '2026-09-01']);

        app(MonthlyAttendanceProcessor::class)->process(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-10'),
            createMissing: true,
            force: true,
            alwaysSummarize: true,
        );

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-01',
            'status' => 'off_day',
            'off_day_days' => 1,
            'source' => 'missing_policy',
        ]);
        // Friday remains the weekly day off, and today is still in progress.
        $this->assertDatabaseMissing('attendances', ['employee_id' => $employee->id, 'date' => '2026-09-04']);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $employee->id, 'date' => '2026-09-10']);
        $this->assertSame(8, Attendance::where('employee_id', $employee->id)->count());

        $summary = MonthlyAttendanceSummary::where('employee_id', $employee->id)
            ->where('year', 2026)->where('month', 9)->firstOrFail();
        $this->assertSame(8.0, (float) $summary->off_days);
        $this->assertSame(0.0, (float) $summary->absent_days);
    }

    public function test_month_generation_creates_only_missing_payslips(): void
    {
        $this->actingAsSuperAdmin();
        Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'decimal_places' => 2]);
        Employee::factory()->create(['join_date' => '2026-01-01', 'status' => 'active', 'salary' => 2500]);
        Employee::factory()->create(['join_date' => '2026-01-01', 'status' => 'active', 'salary' => 1800]);

        $this->postJson('/api/salaries/generate', ['salaryMonth' => '2026-08'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 2)
            ->assertJsonPath('skipped', 0);

        $this->postJson('/api/salaries/generate', ['salaryMonth' => '2026-08'])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 2);

        $this->assertDatabaseCount('salaries', 2);
    }

    public function test_self_editing_requires_its_permission_and_cannot_change_pay_or_position(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'edit_own_employee_profile'],
            ['category' => 'human_resources'],
        );
        $role = Role::create(['name' => 'self_profile_editor']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
            'salary' => 2500,
            'position_id' => Position::factory()->create()->id,
        ]);
        Sanctum::actingAs($user, ['access']);

        $this->putJson("/api/employees/{$employee->id}", [
            'name' => 'Updated Name',
            'contact' => '0700000999',
            'addr' => 'New address',
            'guarantor_name' => 'New Guarantor',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'Updated Name',
            'guarantor_name' => 'New Guarantor',
        ]);

        $this->putJson("/api/employees/{$employee->id}", [
            'name' => 'Updated Name',
            'salary' => 99999,
        ])->assertForbidden();

        $this->assertSame(2500.0, (float) $employee->fresh()->salary);
    }
}
