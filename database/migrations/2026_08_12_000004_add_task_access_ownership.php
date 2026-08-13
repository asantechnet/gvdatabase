<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_user', function (Blueprint $table) {
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['board_id', 'user_id']);
        });

        // Existing task assignees become members of the board that contains
        // their work, preserving visibility when the policy is introduced.
        $memberships = DB::table('tasks')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->select('tasks.board_id', 'task_user.user_id')
            ->distinct()
            ->get();

        foreach ($memberships as $membership) {
            DB::table('board_user')->insertOrIgnore([
                'board_id' => $membership->board_id,
                'user_id' => $membership->user_id,
            ]);
        }

        Schema::table('subtasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('task_id')->constrained()->restrictOnDelete();
        });

        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');
        $subtasks = DB::table('subtasks')->select('id', 'task_id')->orderBy('id')->get();

        foreach ($subtasks as $subtask) {
            $ownerId = DB::table('task_user')->where('task_id', $subtask->task_id)->min('user_id') ?: $fallbackUserId;

            if ($ownerId) {
                DB::table('subtasks')->where('id', $subtask->id)->update(['user_id' => $ownerId]);
            }
        }

        if ($subtasks->isNotEmpty() && ! $fallbackUserId) {
            throw new RuntimeException('Cannot assign owners to existing subtasks without a user account.');
        }

        Schema::table('subtasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('subtasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::dropIfExists('board_user');
    }
};
