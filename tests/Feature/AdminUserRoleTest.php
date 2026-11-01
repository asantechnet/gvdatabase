<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->create([
            'roles' => [$role],
            'acc_status' => 'active',
            ...$attributes,
        ]);
    }

    public function test_admin_user_list_includes_the_primary_account(): void
    {
        $primary = $this->userWithRole('super_admin', [
            'name' => 'Primary Administrator',
            'email' => 'primary@example.test',
        ]);
        $staff = $this->userWithRole('staff');

        Sanctum::actingAs($primary, ['access']);

        $rows = collect($this->getJson('/api/users')->assertOk()->json('data'));

        $this->assertCount(2, $rows);
        $primaryRow = $rows->firstWhere('id', $primary->id);
        $this->assertTrue($primaryRow['is_primary']);
        $this->assertTrue($primaryRow['is_self']);
        $this->assertSame('primary@example.test', $primaryRow['email']);
        $this->assertFalse($rows->firstWhere('id', $staff->id)['is_primary']);
    }

    public function test_primary_account_cannot_be_deleted_by_another_super_admin(): void
    {
        $primary = $this->userWithRole('super_admin');
        $actor = $this->userWithRole('super_admin');

        Sanctum::actingAs($actor, ['access']);

        $this->deleteJson("/api/users/{$primary->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.primary_account_protected');

        $this->assertDatabaseHas('users', ['id' => $primary->id, 'deleted_at' => null]);
    }

    public function test_removing_the_last_super_admin_role_is_rejected(): void
    {
        $primary = $this->userWithRole('super_admin');

        Sanctum::actingAs($primary, ['access']);

        $this->putJson("/api/users/{$primary->id}", ['roles' => ['staff']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.cannot_remove_current_super_admin_role');

        $this->assertTrue($primary->fresh()->isSuperAdmin());
    }

    public function test_in_use_role_exposes_its_count_and_cannot_be_deleted(): void
    {
        $staffRole = Role::query()->where('name', 'staff')->firstOrFail();
        $this->userWithRole('staff');
        $actor = $this->userWithRole('super_admin');

        Sanctum::actingAs($actor, ['access']);

        $role = collect($this->getJson('/api/roles')->assertOk()->json('data'))
            ->firstWhere('name', 'staff');

        $this->assertSame(1, $role['users_count']);
        $this->assertArrayHasKey('permissions', $role);

        $this->deleteJson("/api/roles/{$staffRole->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.role_in_use');

        $this->assertDatabaseHas('roles', ['id' => $staffRole->id]);
    }
}
