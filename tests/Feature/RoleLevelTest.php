<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $actor = User::factory()->create();
        $actor->syncRoles(['super_admin']);
        Sanctum::actingAs($actor, ['access']);
    }

    public function test_a_role_can_be_created_with_a_seniority_level(): void
    {
        $this->postJson('/api/roles', ['name' => 'senior_editor', 'level' => 55])
            ->assertCreated()
            ->assertJsonPath('data.level', 55);

        $this->assertSame(55, (int) Role::query()->where('name', 'senior_editor')->value('level'));
    }

    public function test_a_level_can_be_raised_without_touching_permissions(): void
    {
        $role = Role::create(['name' => 'editor', 'level' => 40]);
        $role->permissions()->sync(
            Permission::query()->where('name', 'view_roles')->pluck('id')->all(),
        );

        $this->putJson("/api/roles/{$role->id}", ['level' => 65])
            ->assertOk()
            ->assertJsonPath('data.level', 65);

        $this->assertSame(65, (int) $role->fresh()->level);
        $this->assertSame(['view_roles'], $role->fresh()->permissions()->pluck('name')->all());
    }

    public function test_a_level_change_requires_the_hierarchy_permission(): void
    {
        $role = Role::create(['name' => 'editor', 'level' => 40]);

        $editorRole = Role::create(['name' => 'role_editor', 'level' => 400]);
        $editorRole->permissions()->sync(
            Permission::query()->whereIn('name', ['view_roles', 'edit_roles'])->pluck('id')->all(),
        );

        $editor = User::factory()->create();
        $editor->syncRoles(['role_editor']);
        Sanctum::actingAs($editor, ['access']);

        $this->putJson("/api/roles/{$role->id}", ['level' => 65])->assertForbidden();
        $this->assertSame(40, (int) $role->fresh()->level);

        // The same user may still rename the role; only the level is guarded.
        $this->putJson("/api/roles/{$role->id}", ['name' => 'content_editor'])->assertOk();
    }

    public function test_a_protected_role_level_cannot_be_changed(): void
    {
        $role = Role::query()->where('name', 'super_admin')->firstOrFail();
        $before = (int) $role->level;

        $this->putJson("/api/roles/{$role->id}", ['level' => $before + 10])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.request_rejected');

        $this->assertSame($before, (int) $role->fresh()->level);
    }

    public function test_invalid_levels_are_rejected(): void
    {
        $role = Role::create(['name' => 'editor', 'level' => 40]);

        foreach ([-1, 1.5, 100001] as $level) {
            $this->putJson("/api/roles/{$role->id}", ['level' => $level])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['level']);
        }

        $this->assertSame(40, (int) $role->fresh()->level);
    }
}
