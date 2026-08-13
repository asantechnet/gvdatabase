<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Board;
use App\Models\Comment;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorthyEmployee;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseFiveIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_and_subtask_metadata_edits_expire_but_completion_remains_available(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $user = User::factory()->create(['roles' => ['staff']]);
        $board = Board::factory()->create();
        $task = Task::factory()->create(['board_id' => $board->id, 'progress' => 0]);
        $board->users()->attach($user);
        $task->users()->attach($user);
        $expiredAt = now('UTC')->subMinutes(61);
        $comment = Comment::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'created_at' => $expiredAt,
            'updated_at' => $expiredAt,
        ]);
        $subtask = Subtask::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'is_completed' => false,
            'created_at' => $expiredAt,
            'updated_at' => $expiredAt,
        ]);

        Sanctum::actingAs($user, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['progress' => 100])
            ->assertStatus(422)
            ->assertJsonPath('errors.progress.0', 'validation.invalid');

        $this->putJson("/api/tasks/{$task->id}/comments/{$comment->id}", ['content' => 'Too late'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.edit_window_expired');
        $this->putJson("/api/tasks/{$task->id}/subtasks/{$subtask->id}", ['title' => 'Too late'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.edit_window_expired');

        $this->putJson("/api/tasks/{$task->id}/subtasks/{$subtask->id}", ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('taskProgress', 100)
            ->assertJsonPath('data.isCompleted', true)
            ->assertJsonPath('data.editWindowExpired', true)
            ->assertJsonPath('data.canEdit', false);
        $this->assertSame(100, $task->fresh()->progress);
    }

    public function test_administrative_edit_window_override_is_explicitly_audited(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        $board = Board::factory()->create();
        $task = Task::factory()->create(['board_id' => $board->id]);
        $comment = Comment::factory()->create([
            'task_id' => $task->id,
            'user_id' => $admin->id,
            'created_at' => now('UTC')->subHours(2),
        ]);

        Sanctum::actingAs($admin, ['access']);
        $this->putJson("/api/tasks/{$task->id}/comments/{$comment->id}", ['content' => 'Approved correction'])
            ->assertOk()
            ->assertJsonPath('data.content', 'Approved correction')
            ->assertJsonPath('data.canEdit', true);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'edit_window_overridden',
            'table_name' => 'comments',
            'auditable_type' => Comment::class,
            'auditable_id' => $comment->id,
            'user_id' => $admin->id,
        ]);
        $this->assertSame(
            $comment->created_at->copy()->utc()->addHour()->toISOString(),
            AuditLog::where('event', 'edit_window_overridden')->firstOrFail()->old_values['editable_until'],
        );
    }

    public function test_salary_keeps_its_currency_and_rate_snapshot_after_currency_changes(): void
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        $employee = Employee::factory()->create();
        $currency = Currency::factory()->create([
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'decimal_places' => 2,
            'rate_against_base' => 1.25,
        ]);
        Sanctum::actingAs($admin, ['access']);

        $response = $this->postJson('/api/salaries', [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 100.25,
            'workedDays' => 30,
            'totalDays' => 30,
            'remarks' => 'Snapshot test',
            'date' => '2026-08-13',
            'deductions' => [],
        ])->assertCreated();

        $salaryId = $response->json('data.id');
        $currency->update([
            'name' => 'Changed Dollar',
            'code' => 'XXX',
            'symbol' => '!',
            'decimal_places' => 4,
            'rate_against_base' => 9,
        ]);

        $this->getJson("/api/salaries/{$salaryId}")
            ->assertOk()
            ->assertJsonPath('data.currency.code', 'USD')
            ->assertJsonPath('data.currency.name', 'US Dollar')
            ->assertJsonPath('data.currency.symbol', '$')
            ->assertJsonPath('data.currency.decimalPlaces', 2)
            ->assertJsonPath('data.currency.rateToBase', 1.25)
            ->assertJsonPath('data.amountPaidBase', 125.3125)
            ->assertJsonPath('data.currencySnapshotSource', 'recorded_at_creation');

        $this->putJson("/api/salaries/{$salaryId}", [
            'employee_id' => $employee->id,
            'currency_id' => Currency::factory()->create()->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 100.25,
            'workedDays' => 30,
            'totalDays' => 30,
            'remarks' => 'Snapshot test',
            'date' => '2026-08-13',
            'deductions' => [],
        ])->assertStatus(422)->assertJsonPath('code', 'business.salary_currency_immutable');
    }

    public function test_bonus_and_salary_preserve_each_conversion_stage(): void
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        $employee = Employee::factory()->create();
        $usd = Currency::factory()->create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'rate_against_base' => 2]);
        $eur = Currency::factory()->create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'rate_against_base' => 4]);
        $afn = Currency::factory()->create(['name' => 'Afghani', 'code' => 'AFN', 'symbol' => '؋', 'rate_against_base' => .5]);
        Sanctum::actingAs($admin, ['access']);

        $created = $this->postJson('/api/worthy-employees', [
            'employeeId' => $employee->id,
            'performanceScore' => 4.5,
            'eligibilityReason' => 'Top Performer',
            'achievementDetails' => 'Exceeded the delivery target.',
            'suggestedAmount' => 100,
            'currencyId' => $usd->id,
            'selectedAmount' => 50,
            'selectedCurrencyId' => $eur->id,
            'selectedType' => 'Performance',
            'bonusMonth' => '2026-08',
        ])->assertCreated()
            ->assertJsonPath('data.suggestedRateToBase', 2)
            ->assertJsonPath('data.suggestedBaseAmount', 200)
            ->assertJsonPath('data.selectedRateToBase', 4)
            ->assertJsonPath('data.selectedBaseAmount', 200);

        $bonusId = $created->json('data.id');
        $eur->update(['rate_against_base' => 5]);
        $this->patchJson("/api/worthy-employees/{$bonusId}", ['isGranted' => true])
            ->assertOk()
            ->assertJsonPath('data.selectedRateToBase', 4)
            ->assertJsonPath('data.grantedRateToBase', 5)
            ->assertJsonPath('data.grantedBaseAmount', 250);

        $salary = $this->postJson('/api/salaries', [
            'employee_id' => $employee->id,
            'currency_id' => $afn->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 1000,
            'workedDays' => 30,
            'totalDays' => 30,
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [],
        ])->assertCreated()
            ->assertJsonPath('data.bonus', 500)
            ->assertJsonPath('data.bonusBase', 250)
            ->assertJsonPath('data.bonuses.0.salaryConversion.sourceCurrencyCode', 'EUR')
            ->assertJsonPath('data.bonuses.0.salaryConversion.sourceRateToBase', 5)
            ->assertJsonPath('data.bonuses.0.salaryConversion.salaryCurrencyCode', 'AFN')
            ->assertJsonPath('data.bonuses.0.salaryConversion.salaryRateToBase', .5)
            ->assertJsonPath('data.bonuses.0.salaryConversion.convertedAmount', 500);

        $this->assertDatabaseHas('salary_worthy_employee', [
            'salary_id' => $salary->json('data.id'),
            'worthy_employee_id' => $bonusId,
            'source_currency_code' => 'EUR',
            'salary_currency_code' => 'AFN',
            'conversion_snapshot_source' => 'recorded_at_inclusion',
        ]);
        $this->assertSame('recorded_at_creation', WorthyEmployee::findOrFail($bonusId)->currency_snapshot_source);
    }
}
