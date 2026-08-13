<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Board;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Subtask;
use App\Models\Task;
use Database\Seeders\SubtaskSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReportedIssueRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($this->user, ['access']);
    }

    public function test_deleted_attendance_can_be_recorded_again_for_the_same_employee_and_date(): void
    {
        $employee = Employee::factory()->create();
        $payload = ['staff_id' => $employee->id, 'date' => '2026-08-11', 'status' => 'present'];

        $created = $this->postJson('/api/attendances', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.staff_id', $employee->id);

        $attendanceId = $created->json('data.id');
        $this->deleteJson("/api/attendances/{$attendanceId}")->assertOk();

        $this->postJson('/api/attendances', [...$payload, 'status' => 'absent'])
            ->assertSuccessful()
            ->assertJsonPath('data.id', $attendanceId)
            ->assertJsonPath('data.status', 'absent');

        $this->assertSame(1, Attendance::withTrashed()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('attendances', ['id' => $attendanceId, 'deleted_at' => null, 'status' => 'absent']);
    }

    public function test_switching_base_currency_clears_the_previous_base_immediately(): void
    {
        $oldBase = Currency::factory()->create(['code' => 'USD', 'is_base' => true, 'rate_against_base' => 1]);
        $newBase = Currency::factory()->create(['code' => 'AFN', 'is_base' => false, 'rate_against_base' => 70]);

        $this->putJson("/api/currencies/{$newBase->id}", ['is_base' => true])
            ->assertOk()
            ->assertJsonPath('data.isBase', true)
            ->assertJsonPath('data.rateToBase', 1);

        $this->assertFalse($oldBase->fresh()->is_base);
        $this->assertTrue($newBase->fresh()->is_base);
    }

    public function test_in_app_notification_preferences_filter_future_deliveries(): void
    {
        $this->user->update([
            'account_settings' => ['notifications' => ['finance_updates' => ['in_app' => false]]],
        ]);

        $financeNotification = Notification::create([
            'type' => 'finance',
            'notification_key' => 'invoice.paid',
            'date_time' => now(),
        ]);
        $financeNotification->deliverTo([$this->user->id]);

        $workNotification = Notification::create([
            'type' => 'task',
            'notification_key' => 'task.updated',
            'date_time' => now(),
        ]);
        $workNotification->deliverTo([$this->user->id]);

        $this->assertDatabaseMissing('notification_user', [
            'notification_id' => $financeNotification->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $workNotification->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_marketers_and_offices_support_crud(): void
    {
        $marketer = $this->postJson('/api/marketers', [
            'name' => 'North Region Team',
            'email' => 'north@example.test',
            'phone' => '+93 700 000 001',
            'status' => 'active',
            'notes' => 'Enterprise accounts',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/marketers/{$marketer}", [
            'name' => 'North Region',
            'email' => 'north@example.test',
            'phone' => '+93 700 000 001',
            'status' => 'inactive',
            'notes' => null,
        ])->assertOk()->assertJsonPath('data.name', 'North Region');
        $this->deleteJson("/api/marketers/{$marketer}")->assertOk();

        $office = $this->postJson('/api/offices', [
            'name' => 'Central Office',
            'code' => 'CENTRAL',
            'email' => 'central@example.test',
            'phone' => '+93 700 000 002',
            'address' => 'Kabul',
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/offices/{$office}", [
            'name' => 'Head Office',
            'code' => 'HQ',
            'email' => 'central@example.test',
            'phone' => '+93 700 000 002',
            'address' => 'Kabul',
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.code', 'HQ');
        $this->deleteJson("/api/offices/{$office}")->assertOk();
    }

    public function test_contract_template_create_update_and_delete_stay_consistent(): void
    {
        $template = $this->postJson('/api/contract-templates', [
            'name' => 'Standard Service Agreement',
            'type' => 'Service',
            'cover_text' => 'Cover',
            'content' => '<p>Terms</p>',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Standard Service Agreement')
            ->json('data.id');

        $this->putJson("/api/contract-templates/{$template}", [
            'name' => 'Updated Service Agreement',
            'content' => '<p>Updated terms</p>',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Service Agreement')
            ->assertJsonPath('data.content', '<p>Updated terms</p>');

        $this->deleteJson("/api/contract-templates/{$template}")->assertOk();
        $this->assertSoftDeleted('contract_templates', ['id' => $template]);
    }

    public function test_contract_response_keeps_template_name_and_cover_description_distinct(): void
    {
        $template = \App\Models\ContractTemplate::factory()->create([
            'name' => 'Employment Agreement',
            'cover_text' => 'A confidential employment arrangement.',
        ]);
        $contract = \App\Models\Contract::create([
            'template_id' => $template->id,
            'status' => 'Active',
            'start_date' => '2026-08-13',
            'counterparty' => 'Jane Doe',
        ]);

        $this->getJson("/api/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.contract', 'Employment Agreement')
            ->assertJsonPath('data.contract_template.name', 'Employment Agreement')
            ->assertJsonPath('data.contract_template.cover_text', 'A confidential employment arrangement.');
    }

    public function test_paid_time_off_creates_updates_and_removes_attendance(): void
    {
        Setting::create([
            'navigation_clr' => '#ffffff', 'cards_clr' => '#ffffff', 'footer_clr' => '#ffffff',
            'paid_off_days_per_year' => 10, 'website_title' => 'Test', 'website_address' => 'https://example.test',
            'phone' => '123', 'physical_address' => 'Test address', 'short_company_name' => 'Test',
            'original_company_name' => 'Test Company',
        ]);
        $employee = Employee::factory()->create();

        $leaveId = $this->postJson("/api/employees/{$employee->id}/paid-time-offs", [
            'date' => '2026-08-20', 'reason' => 'Annual leave',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id, 'date' => '2026-08-20', 'status' => 'paid-off-day', 'deleted_at' => null,
        ]);

        $this->putJson("/api/employees/{$employee->id}/paid-time-offs/{$leaveId}", [
            'date' => '2026-08-21', 'reason' => 'Changed day',
        ])->assertOk();
        $this->assertSoftDeleted('attendances', ['employee_id' => $employee->id, 'date' => '2026-08-20']);
        $this->assertDatabaseHas('attendances', ['employee_id' => $employee->id, 'date' => '2026-08-21', 'status' => 'paid-off-day']);

        $this->deleteJson("/api/employees/{$employee->id}/paid-time-offs/{$leaveId}")->assertOk();
        $this->assertSoftDeleted('attendances', ['employee_id' => $employee->id, 'date' => '2026-08-21']);
    }

    public function test_admin_invitation_verifies_email_then_requires_user_created_password(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Invited User', 'email' => 'invited@example.test', 'phone' => '123456',
            'address' => 'Kabul', 'roles' => ['staff'], 'acc_status' => 'active',
        ])->assertCreated()->assertJsonPath('data.password_setup_pending', true);

        $invited = User::findOrFail($response->json('data.id'));
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $invited->id, 'hash' => sha1($invited->email),
        ]);
        $redirect = $this->get($verificationUrl)->assertRedirect();
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/complete-invitation', [
            'email' => $invited->email, 'token' => $query['token'],
            'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password',
        ])->assertOk();

        $invited->refresh();
        $this->assertTrue($invited->hasVerifiedEmail());
        $this->assertFalse($invited->must_set_password);
        $this->assertTrue(Hash::check('new-secure-password', $invited->password));
    }

    public function test_subtask_seeder_always_assigns_a_valid_owner(): void
    {
        $board = Board::factory()->create();
        $tasks = Task::factory()->count(20)->create(['board_id' => $board->id]);
        foreach ($tasks as $task) {
            $task->users()->attach($this->user->id);
        }

        mt_srand(1234);
        $this->seed(SubtaskSeeder::class);

        $this->assertGreaterThan(0, Subtask::count());
        $this->assertSame(0, Subtask::whereNull('user_id')->count());
        $this->assertSame(0, Subtask::whereDoesntHave('user')->count());
    }
}
