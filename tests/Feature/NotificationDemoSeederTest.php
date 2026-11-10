<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Support\AgencyStaffAssignment;
use Database\Seeders\NotificationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function demoUser(): User
    {
        return User::where('email', NotificationDemoSeeder::DEMO_EMAIL)->sole();
    }

    /** @return list<string> */
    private function heartbeatKeys(): array
    {
        $keys = collect($this->getJson('/api/notifications/heartbeat')->assertOk()->json('data'))
            ->pluck('notification_key')
            ->all();

        return array_map('strval', $keys);
    }

    public function test_the_demo_account_is_validated_for_both_specialties(): void
    {
        $this->seed(NotificationDemoSeeder::class);

        $user = $this->demoUser();
        $employee = $user->employee;

        $this->assertSame('active', $user->acc_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->must_set_password);
        $this->assertTrue(Hash::check(NotificationDemoSeeder::DEMO_PASSWORD, $user->password));
        $this->assertTrue($user->roles->contains('name', 'counselor'));
        $this->assertTrue($user->roles->contains('name', 'admission'));

        // The specialist pickers accept the single employee for both roles.
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->status);
        $this->assertTrue(AgencyStaffAssignment::query('counselor')->whereKey($employee->id)->exists());
        $this->assertTrue(AgencyStaffAssignment::query('admission')->whereKey($employee->id)->exists());
    }

    public function test_the_seeded_task_and_contract_notify_the_demo_account_over_the_heartbeat(): void
    {
        $this->seed(NotificationDemoSeeder::class);

        $user = $this->demoUser();
        $employee = $user->employee;
        $applicant = Applicant::where('email', NotificationDemoSeeder::APPLICANT_EMAIL)->sole();

        // The case carries a counselor and a plural responsible employee.
        $this->assertSame($employee->id, $applicant->responsible_employee_id);
        $this->assertTrue($applicant->responsibleEmployees()->whereKey($employee->id)->exists());

        $task = Task::query()->sole();
        $this->assertSame($applicant->id, $task->applicant_id);
        $this->assertTrue($task->users->contains($user->id));

        $primary = Contract::query()->where('contract_role', Contract::ROLE_PRIMARY)->sole();
        $extension = Contract::query()->where('contract_role', Contract::ROLE_EXTENSION)->sole();
        $this->assertSame($applicant->id, $primary->applicant_id);
        $this->assertSame($primary->id, $extension->parent_contract_id);
        $this->assertNotNull($primary->contract_number);

        foreach ([
            "task.assigned.{$task->id}.{$user->id}",
            "contract.generated.{$primary->id}",
            "contract.generated.{$extension->id}",
            "applicant.assigned.{$applicant->id}.{$user->id}",
        ] as $key) {
            $this->assertNotNull(
                Notification::query()->where('notification_key', $key)->first(),
                "Missing notification {$key}",
            );
        }

        $this->assertTrue($user->fresh()->unread_notifications);

        // The signed-in client pulls the batch over the real-time channel.
        Sanctum::actingAs($user->fresh(), ['access']);
        $keys = $this->heartbeatKeys();

        $this->assertCount(5, $keys);
        $this->assertContains("task.assigned.{$task->id}.{$user->id}", $keys);
        $this->assertFalse($user->fresh()->unread_notifications);
    }

    public function test_rerunning_queues_one_fresh_task_and_extension_contract(): void
    {
        $this->seed(NotificationDemoSeeder::class);

        $user = $this->demoUser();
        $applicant = Applicant::where('email', NotificationDemoSeeder::APPLICANT_EMAIL)->sole();

        Sanctum::actingAs($user->fresh(), ['access']);
        $delivered = $this->heartbeatKeys();

        $this->seed(NotificationDemoSeeder::class);

        // Only the repeatable records are duplicated; the case and its primary
        // contract are reused, and their notifications are not re-delivered.
        $this->assertSame(1, Applicant::query()->count());
        $this->assertSame(2, Task::query()->count());
        $this->assertSame(1, Contract::query()->where('contract_role', Contract::ROLE_PRIMARY)->count());
        $this->assertSame(2, Contract::query()->where('contract_role', Contract::ROLE_EXTENSION)->count());
        $this->assertSame(1, Notification::query()->where('notification_key', 'like', "applicant.admission.assigned.{$applicant->id}.%")->count());

        $second = Task::query()->where('title', NotificationDemoSeeder::TASK_TITLE_PREFIX.'2')->sole();
        $secondExtension = Contract::query()
            ->where('contract_role', Contract::ROLE_EXTENSION)
            ->orderByDesc('id')
            ->first();

        $this->assertTrue($user->fresh()->unread_notifications);

        Sanctum::actingAs($user->fresh(), ['access']);
        $keys = $this->heartbeatKeys();

        // The batch re-includes still-unread items (the client de-dupes by id),
        // so assert the delta: exactly the fresh task and the fresh extension.
        $fresh = array_values(array_diff($keys, $delivered));
        sort($fresh);
        $expected = ["contract.generated.{$secondExtension->id}", "task.assigned.{$second->id}.{$user->id}"];
        sort($expected);

        $this->assertSame($expected, $fresh);
    }
}
