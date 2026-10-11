<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class RoleHierarchyScopeTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_senior_counselor_can_read_but_not_change_a_direct_reports_case(): void
    {
        $senior = $this->user('senior_counselor');
        $counselor = $this->user('counselor');
        $seniorEmployee = Employee::factory()->create(['user_id' => $senior->id]);
        $counselorEmployee = Employee::factory()->create([
            'user_id' => $counselor->id,
            'supervisor_id' => $seniorEmployee->id,
        ]);
        $case = Applicant::factory()->create(['responsible_employee_id' => $counselorEmployee->id]);

        Sanctum::actingAs($senior, ['access']);

        $this->getJson('/api/applicants')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $case->id);
        $this->getJson("/api/applicants/{$case->id}")->assertOk();
        $this->putJson("/api/applicants/{$case->id}", ['notes' => 'Attempted edit'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_notification_senders_are_limited_to_their_team_unless_they_have_the_broadcast_grant(): void
    {
        Role::where('name', 'manager')->firstOrFail()->permissions()
            ->detach(Permission::where('name', 'send_all_notifications')->value('id'));
        $manager = $this->user('manager');
        $staff = $this->user('staff');
        $outsider = $this->user('staff');
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);
        Employee::factory()->create(['user_id' => $staff->id, 'supervisor_id' => $managerEmployee->id]);
        Employee::factory()->create(['user_id' => $outsider->id]);

        Sanctum::actingAs($manager, ['access']);

        $payload = ['type' => 'task', 'notification_key' => 'test.team.notice', 'user_ids' => [$staff->id]];
        $this->postJson('/api/notifications', $payload)->assertCreated();
        $this->assertDatabaseHas('notification_user', ['user_id' => $staff->id]);

        $this->postJson('/api/notifications', [...$payload, 'notification_key' => 'test.outside.notice', 'user_ids' => [$outsider->id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
        $this->postJson('/api/notifications', ['type' => 'task', 'notification_key' => 'test.broadcast.notice'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_senior_counselor_cannot_edit_a_subordinates_service_sale(): void
    {
        $senior = $this->user('senior_counselor');
        $counselor = $this->user('counselor');
        $seniorEmployee = Employee::factory()->create(['user_id' => $senior->id]);
        $counselorEmployee = Employee::factory()->create([
            'user_id' => $counselor->id,
            'supervisor_id' => $seniorEmployee->id,
        ]);
        Sanctum::actingAs($counselor, ['access']);
        $sale = $this->createBuiltInContract('test_offer', ['fee' => 500]);

        Sanctum::actingAs($senior, ['access']);
        $this->getJson("/api/contracts/{$sale['id']}")->assertOk();
        $this->putJson("/api/contracts/{$sale['id']}", ['name' => 'Unauthorized change'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['roles' => [$role], 'acc_status' => 'active']);
    }
}
