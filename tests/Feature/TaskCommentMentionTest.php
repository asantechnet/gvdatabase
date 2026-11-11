<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Support\ServiceBoard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskCommentMentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_creator_can_edit_a_comment_and_only_within_thirty_minutes(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $creator = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $peer = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $task = Task::factory()->create([
            'board_id' => ServiceBoard::personalFor($creator)->id,
            'created_by' => $creator->id,
            'title' => 'Comment edit coverage',
        ]);
        $task->users()->attach([$creator->id, $peer->id]);

        Sanctum::actingAs($creator, ['access']);
        $commentId = $this->postJson("/api/tasks/{$task->id}/comments", [
            'content' => 'Original comment.',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($peer, ['access']);
        $this->putJson("/api/tasks/{$task->id}/comments/{$commentId}", [
            'content' => 'Peer edit attempt.',
        ])->assertForbidden();

        Sanctum::actingAs($creator, ['access']);
        $this->putJson("/api/tasks/{$task->id}/comments/{$commentId}", [
            'content' => 'Creator edit inside the window.',
        ])->assertOk();

        Comment::findOrFail($commentId)->forceFill(['created_at' => now()->subMinutes(31)])->save();
        $this->putJson("/api/tasks/{$task->id}/comments/{$commentId}", [
            'content' => 'Late edit attempt.',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.comment_edit_window_expired');
    }

    public function test_task_comments_notify_assignees_and_only_eligible_mentions(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $actor = User::factory()->create(['roles' => ['super_admin'], 'acc_status' => 'active']);
        $mentioned = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $coworker = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $outsider = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);

        $task = Task::factory()->create([
            'board_id' => ServiceBoard::personalFor($actor)->id,
            'created_by' => $actor->id,
            'title' => 'Mention coverage task',
        ]);
        $task->users()->attach([$actor->id, $mentioned->id, $coworker->id]);

        Sanctum::actingAs($actor, ['access']);

        $this->postJson("/api/tasks/{$task->id}/comments", [
            'content' => '@mentioned please review this task.',
            'mention_ids' => [$mentioned->id, $outsider->id],
        ])->assertCreated();

        $comment = Comment::query()->latest('id')->firstOrFail();

        $this->assertSame(
            [$mentioned->id],
            $comment->mentionedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        );

        $mention = Notification::query()
            ->where('notification_key', "task.mention.{$comment->id}.{$mentioned->id}")
            ->sole();
        $this->assertSame(
            [$mentioned->id],
            $mention->recipients()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        );

        $commentNotice = Notification::query()
            ->where('notification_key', "task.comment.{$comment->id}.{$coworker->id}")
            ->sole();
        $this->assertSame(
            [$coworker->id],
            $commentNotice->recipients()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        );

        $this->assertFalse(Notification::query()
            ->where('notification_key', "task.mention.{$comment->id}.{$outsider->id}")
            ->exists());
        $this->assertFalse(Notification::query()
            ->where('notification_key', "task.comment.{$comment->id}.{$actor->id}")
            ->exists());
    }
}
