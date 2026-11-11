<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Board;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditWindowPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_edits_a_fresh_employee_but_is_blocked_after_the_window(): void
    {
        $manager = User::factory()->create(['roles' => ['manager']]);
        $employee = Employee::factory()->create([
            'user_id' => $manager->id,
            'created_at' => now('UTC'),
        ]);

        $this->assertTrue($manager->hasPermission('edit_employees'));
        $this->assertFalse($manager->hasPermission('override_edit_window'));

        Sanctum::actingAs($manager, ['access']);

        $this->putJson("/api/employees/{$employee->id}", ['name' => 'Fresh employee edit'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Fresh employee edit');

        $employee->forceFill(['created_at' => now('UTC')->subMinutes(61)])->saveQuietly();

        $this->putJson("/api/employees/{$employee->id}", ['name' => 'Expired employee edit'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.edit_window_expired');

        $this->assertSame('Fresh employee edit', $employee->fresh()->name);
    }

    public function test_override_holder_saves_an_expired_employee_and_the_override_is_audited(): void
    {
        $admin = User::factory()->create(['roles' => ['admin']]);
        $employee = Employee::factory()->create([
            'created_at' => now('UTC')->subHours(2),
        ]);

        Sanctum::actingAs($admin, ['access']);

        $this->putJson("/api/employees/{$employee->id}", ['name' => 'Audited correction'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Audited correction');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'edit_window_overridden',
            'table_name' => 'employees',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'user_id' => $admin->id,
        ]);

        $audit = AuditLog::query()
            ->where('event', 'edit_window_overridden')
            ->where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->firstOrFail();

        $this->assertSame(
            $employee->created_at->copy()->utc()->addHour()->toISOString(),
            $audit->old_values['editable_until'],
        );
    }

    public function test_task_and_contract_edits_are_exempt_from_the_employee_window(): void
    {
        $manager = User::factory()->create(['roles' => ['manager']]);
        $board = Board::factory()->create();
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'created_at' => now('UTC')->subHours(2),
        ]);

        $otherType = ContractType::query()->where('slug', 'other')->firstOrFail();
        $template = ContractTemplate::factory()->create([
            'contract_type_id' => $otherType->id,
            'type' => 'Other',
        ]);
        $contract = Contract::factory()->create([
            'template_id' => $template->id,
            'status' => 'draft',
            'created_at' => now('UTC')->subHours(2),
        ]);

        Sanctum::actingAs($manager, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Late task edit'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Late task edit');

        $this->putJson("/api/contracts/{$contract->id}", ['name' => 'Late contract edit'])
            ->assertOk()
            ->assertJsonPath('data.contract', 'Late contract edit');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'name' => 'Late contract edit',
        ]);
    }

    public function test_employee_document_routes_are_outside_the_window(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['roles' => ['manager']]);
        $employee = Employee::factory()->create([
            'user_id' => $manager->id,
            'created_at' => now('UTC')->subHours(2),
        ]);

        Sanctum::actingAs($manager, ['access']);

        $this->post("/api/employees/{$employee->id}/document", [
            'document' => UploadedFile::fake()->create('employee-document.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $employee->refresh();
        $this->assertSame('employee-document.pdf', $employee->document_name);
        Storage::disk('local')->assertExists($employee->document_path);
    }
}
