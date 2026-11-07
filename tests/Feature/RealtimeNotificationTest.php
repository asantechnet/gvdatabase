<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Applicant;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class RealtimeNotificationTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());

        return $user;
    }

    private function actingAsRole(string $role): User
    {
        $user = $this->userWithRole($role);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    /** @param array<int, int> $userIds */
    private function deliver(array $attributes, array $userIds): Notification
    {
        $notification = Notification::create([...$attributes, 'date_time' => now()]);
        $notification->deliverTo($userIds);

        return $notification;
    }

    public function test_delivery_raises_the_flag_only_for_the_recipients(): void
    {
        $recipient = $this->userWithRole('viewer');
        $bystander = $this->userWithRole('viewer');

        $this->deliver([
            'type' => 'task',
            'notification_key' => "task.assigned.1.{$recipient->id}",
            'title' => 'Task assigned',
            'message' => 'Task "Call the student" was assigned to you.',
        ], [$recipient->id]);

        $this->assertTrue($recipient->fresh()->unread_notifications);
        $this->assertFalse($bystander->fresh()->unread_notifications);
        $this->assertSame(1, $recipient->notifications()->count());
        $this->assertSame(0, $bystander->notifications()->count());
    }

    public function test_recipients_who_disabled_in_app_delivery_are_not_flagged(): void
    {
        $optedOut = User::factory()->create([
            'acc_status' => 'active',
            'account_settings' => ['notifications' => ['work_updates' => ['in_app' => false]]],
        ]);
        $optedOut->roles()->attach(Role::query()->where('name', 'viewer')->firstOrFail());

        $this->deliver([
            'type' => 'task',
            'notification_key' => "task.assigned.9.{$optedOut->id}",
            'title' => 'Task assigned',
            'message' => 'Task was assigned to you.',
        ], [$optedOut->id]);

        $this->assertSame(0, $optedOut->notifications()->count());
        $this->assertFalse($optedOut->fresh()->unread_notifications);
    }

    public function test_heartbeat_returns_the_batch_once_and_clears_the_flag(): void
    {
        $user = $this->userWithRole('viewer');
        $key = "task.assigned.2.{$user->id}";
        $this->deliver([
            'type' => 'task',
            'notification_key' => $key,
            'title' => 'Task assigned',
            'message' => 'Task was assigned to you.',
        ], [$user->id]);

        // The request reads the user as stored, with the delivery flag raised.
        Sanctum::actingAs($user->refresh(), ['access']);

        $this->getJson('/api/notifications/heartbeat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notification_key', $key)
            ->assertJsonPath('data.0.is_read', null);

        $this->assertFalse($user->fresh()->unread_notifications);

        // The batch was drained: the next poll must not repeat it.
        $this->getJson('/api/notifications/heartbeat')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_heartbeat_with_a_clear_flag_is_empty_and_reads_no_notification_content(): void
    {
        $user = $this->actingAsRole('viewer');

        // An unread notification exists, but no flag was raised for this user,
        // so the heartbeat must answer from the flag alone.
        $notification = Notification::create([
            'type' => 'task',
            'notification_key' => "task.assigned.3.{$user->id}",
            'title' => 'Task assigned',
            'message' => 'Task was assigned to you.',
            'date_time' => now(),
        ]);
        $notification->recipients()->attach($user->id, ['is_read' => null]);

        DB::enableQueryLog();
        $this->getJson('/api/notifications/heartbeat')->assertOk()->assertJsonCount(0, 'data');
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertFalse($user->fresh()->unread_notifications);
        $this->assertFalse(
            $queries->contains(fn (string $query) => str_contains($query, 'from "notifications"') || str_contains($query, 'from `notifications`')),
            'The heartbeat must not read notification content while the flag is clear.',
        );
    }

    public function test_heartbeat_requires_the_view_own_notifications_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['acc_status' => 'active']), ['access']);

        $this->getJson('/api/notifications/heartbeat')->assertForbidden();
    }

    public function test_task_creation_notifies_every_assignee_and_not_the_actor(): void
    {
        $actor = $this->actingAsRole('super_admin');
        $first = $this->userWithRole('staff');
        $second = $this->userWithRole('staff');
        $board = Board::factory()->create();

        $this->postJson('/api/tasks', [
            'board_id' => $board->id,
            'title' => 'Call the student',
            'assignees' => [$first->id, $second->id],
        ])->assertCreated();

        foreach ([$first, $second] as $assignee) {
            $this->assertTrue($assignee->fresh()->unread_notifications);
            $this->assertSame(1, $assignee->notifications()->where('notification_key', 'like', 'task.assigned.%')->count());
        }

        // The person who assigned the work is not told about their own action.
        $this->assertFalse($actor->fresh()->unread_notifications);
        $this->assertSame(0, $actor->notifications()->count());
    }

    public function test_contract_generation_notifies_the_admission_employee(): void
    {
        $this->actingAsRole('super_admin');
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $responsible = Employee::factory()->create(['status' => 'active']);
        $admission = Employee::factory()->create([
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
        ]);

        // Admission assignment is a scholarship/private field; academy
        // contracts intentionally prohibit it.
        $created = $this->createBuiltInContract('scholarship', [
            'responsible_employee_id' => $responsible->id,
            'admission_employee_id' => $admission->id,
            'currency_id' => $currency->id,
        ]);

        $notification = Notification::query()
            ->where('notification_key', "contract.generated.{$created['id']}")
            ->firstOrFail();
        $recipients = $notification->recipients()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([(int) $admission->user_id], $recipients);
        $this->assertTrue(User::query()->findOrFail($admission->user_id)->unread_notifications);
    }

    public function test_admission_reassignment_notifies_the_new_employee_even_when_they_are_the_actor(): void
    {
        $manager = $this->actingAsRole('manager');
        $admission = Employee::factory()->create([
            'user_id' => $manager->id,
            'status' => 'active',
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
        ]);
        $applicant = Applicant::factory()->create(['admission_employee_id' => null]);

        $this->putJson("/api/applicants/{$applicant->id}", ['admission_employee_id' => $admission->id])->assertOk();

        $notification = Notification::query()->where('notification_key', 'like', "applicant.admission.assigned.{$applicant->id}.{$admission->id}.%")
            ->sole();
        $this->assertSame([$manager->id], $notification->recipients()->pluck('users.id')->all());
    }

    public function test_draft_finance_record_notifies_approvers_and_never_the_recorder(): void
    {
        $this->seed(FinanceTypeSeeder::class);
        $recorder = $this->userWithRole('staff');
        $finance = $this->userWithRole('finance_officer');
        $viewer = $this->userWithRole('viewer');
        Employee::factory()->create(['user_id' => $recorder->id, 'status' => 'active']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);

        Sanctum::actingAs($recorder, ['access']);
        $draft = $this->postJson('/api/incomes', [
            'type' => 'Service',
            'description' => 'Applicant deposit',
            'amount' => 100,
            'quantity' => 1,
            'currency_id' => $currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
            'status' => 'draft',
        ])->assertCreated()->json('data');

        $notification = Notification::query()
            ->where('notification_key', "finance.draft.submitted.income.{$draft['id']}")
            ->firstOrFail();
        $recipients = $notification->recipients()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($finance->id, $recipients);
        $this->assertNotContains($recorder->id, $recipients);
        $this->assertNotContains($viewer->id, $recipients);
        $this->assertTrue($finance->fresh()->unread_notifications);
        // No permission, no notification — and no leftover flag either.
        $this->assertFalse($recorder->fresh()->unread_notifications);
        $this->assertFalse($viewer->fresh()->unread_notifications);

        // The approver pulls it over the real-time channel.
        Sanctum::actingAs($finance->refresh(), ['access']);
        $this->getJson('/api/notifications/heartbeat')
            ->assertOk()
            ->assertJsonPath('data.0.notification_key', "finance.draft.submitted.income.{$draft['id']}");
    }
}
