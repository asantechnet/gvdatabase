<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task management section: boards, tasks and their collaborators,
 * dependencies, subtasks, comments, attachments and service assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('order', 'boards_order_index');
        });

        Schema::create('board_user', function (Blueprint $table) {
            $table->foreignId('board_id');
            $table->foreignId('user_id');

            $table->primary(['board_id', 'user_id']);
            $table->foreign('board_id')->references('id')->on('boards')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('results')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->integer('progress')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->unsignedSmallInteger('rejected_count')->default(0);
            $table->decimal('manager_quality_score', 3, 2)->nullable();
            $table->foreignId('quality_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quality_reviewed_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['completed_at', 'progress'], 'tasks_completed_at_progress_index');
            $table->index('applicant_id', 'tasks_applicant_id_index');
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignId('task_id');
            $table->foreignId('user_id');
            $table->decimal('contribution_percent', 5, 2)->nullable();

            $table->primary(['task_id', 'user_id']);
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('task_tag', function (Blueprint $table) {
            $table->foreignId('task_id');
            $table->foreignId('tag_id');

            $table->primary(['task_id', 'tag_id']);
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignId('task_id');
            $table->foreignId('depends_on_task_id');

            $table->primary(['task_id', 'depends_on_task_id']);
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('depends_on_task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });

        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('title');
            $table->string('category')->default('General');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('section', ['input', 'results'])->default('results');
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('subtasks');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_tag');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('board_user');
        Schema::dropIfExists('boards');
    }
};
