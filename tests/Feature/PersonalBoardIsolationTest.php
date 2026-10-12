<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\ServiceBoard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Personal boards are private: one employee must never see another employee's
 * personal board or the tasks on it, regardless of their board permissions.
 */
class PersonalBoardIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_personal_boards_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $peer = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);

        // Opening the Kanban creates the caller's personal board on demand.
        Sanctum::actingAs($owner, ['access']);
        $ownerBoards = $this->getJson('/api/kanban')->assertOk()->json('data');
        $personalBoard = collect($ownerBoards)->firstWhere('is_personal', true);

        $this->assertNotNull($personalBoard, 'The owner should receive a personal board.');
        $this->assertSame((string) $owner->id, $personalBoard['user_id']);
        $this->assertTrue($personalBoard['is_owned']);

        $task = Task::factory()->create(['board_id' => Board::find($personalBoard['id'])->id, 'title' => 'Owner private work']);
        $task->users()->attach($owner->id);

        // A peer sees only their own personal board, never the owner's.
        Sanctum::actingAs($peer, ['access']);
        $peerBoards = $this->getJson('/api/kanban')->assertOk()->json('data');
        $peerBoardIds = collect($peerBoards)->pluck('id');

        $this->assertFalse(
            $peerBoardIds->contains($personalBoard['id']),
            "A peer must not receive another user's personal board."
        );
        $this->assertTrue(
            collect($peerBoards)->contains(fn ($board) => $board['is_personal'] && $board['is_owned']),
            'The peer should receive their own personal board.'
        );

        // Direct mutation of someone else's personal board is forbidden.
        $this->putJson("/api/boards/{$personalBoard['id']}", ['title' => 'Taken over'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $this->assertSame('Personal', Board::find($personalBoard['id'])->title);
    }

    public function test_personal_boards_are_never_shared_even_with_super_admins(): void
    {
        $owner = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $superAdmin = User::factory()->create(['roles' => ['super_admin'], 'acc_status' => 'active']);

        $personal = ServiceBoard::personalFor($owner);
        $personalTask = Task::factory()->create(['board_id' => $personal->id, 'title' => 'Owner private work']);
        $personalTask->users()->attach($owner->id);

        Sanctum::actingAs($superAdmin, ['access']);
        $boards = $this->getJson('/api/boards')->assertOk()->json('data');

        $this->assertFalse(
            collect($boards)->pluck('id')->contains((string) $personal->id),
            'A super-admin must not receive another user\'s personal board.'
        );

        $this->getJson('/api/kanban')
            ->assertOk()
            ->assertJsonMissing(['id' => (string) $personal->id]);

        $this->putJson("/api/boards/{$personal->id}", ['title' => 'Admin takeover'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $this->putJson("/api/tasks/{$personalTask->id}", ['title' => 'Admin takeover'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }
}
