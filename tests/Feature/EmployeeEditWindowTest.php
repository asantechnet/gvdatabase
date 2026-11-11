<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeEditWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_employee_payload_is_read_only_for_a_non_override_editor(): void
    {
        $manager = User::factory()->create(['roles' => ['manager']]);
        // The lowest-id employee is the hidden primary record.
        Employee::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $manager->id,
            'created_at' => now('UTC')->subMinutes(61),
        ]);

        Sanctum::actingAs($manager, ['access']);

        $this->getJson('/api/employees?per_page=25')
            ->assertOk()
            ->assertJsonPath('data.0.id', $employee->id)
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.editable_until', $this->editableUntil($employee));

        $this->putJson("/api/employees/{$employee->id}", ['name' => 'Bypassed expired edit'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.edit_window_expired');
    }

    public function test_fresh_employee_payload_is_editable_and_saves(): void
    {
        $manager = User::factory()->create(['roles' => ['manager']]);
        // The lowest-id employee is the hidden primary record.
        Employee::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $manager->id,
            'created_at' => now('UTC'),
        ]);

        Sanctum::actingAs($manager, ['access']);

        $this->getJson('/api/employees?per_page=25')
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.editable_until', $this->editableUntil($employee));

        $this->putJson("/api/employees/{$employee->id}", ['name' => 'Fresh employee edit'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Fresh employee edit')
            ->assertJsonPath('data.can_edit', true);
    }

    public function test_override_holder_sees_an_expired_employee_as_editable(): void
    {
        $admin = User::factory()->create(['roles' => ['admin']]);
        // The lowest-id employee is the hidden primary record.
        Employee::factory()->create();
        $employee = Employee::factory()->create([
            'created_at' => now('UTC')->subHours(2),
        ]);

        Sanctum::actingAs($admin, ['access']);

        $this->getJson('/api/employees?per_page=25')
            ->assertOk()
            ->assertJsonPath('data.0.id', $employee->id)
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.editable_until', $this->editableUntil($employee));

        $this->putJson("/api/employees/{$employee->id}", ['name' => 'Override edit'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Override edit')
            ->assertJsonPath('data.can_edit', true);
    }

    private function editableUntil(Employee $employee): string
    {
        return $employee->created_at->copy()->utc()->addHour()->toISOString();
    }
}
