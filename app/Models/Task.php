<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\EmployeeScope;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'board_id',
        'created_by',
        'applicant_id',
        'applicant_contract_id',
        'title',
        'description',
        'priority',
        'progress',
        'start_date',
        'due_date',
        'completed_at',
        'reopened_count',
        'rejected_count',
        'manager_quality_score',
        'quality_reviewed_by',
        'quality_reviewed_at',
        'order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'immutable_datetime',
        'manager_quality_score' => 'float',
        'quality_reviewed_at' => 'immutable_datetime',
        'progress' => 'integer',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Task $task): void {
            if (! $task->isForceDeleting()) {
                return;
            }

            $task->attachments()->each(function (TaskAttachment $attachment): void {
                Storage::disk($attachment->disk)->delete($attachment->path);
            });
        });

        static::creating(function (Task $task): void {
            if ((int) $task->progress >= 100 && ! $task->completed_at) {
                $task->completed_at = now('UTC');
            }
        });

        static::updating(function (Task $task): void {
            if (! $task->isDirty('progress')) {
                return;
            }
            $previous = (int) $task->getOriginal('progress');
            $next = (int) $task->progress;
            if ($previous < 100 && $next >= 100) {
                $task->completed_at = now('UTC');
            }
            if ($previous >= 100 && $next < 100) {
                $task->reopened_count = (int) $task->reopened_count + 1;
                $task->completed_at = null;
            }
        });
    }

    /**
     * Tasks visible in a board listing. A personal-board holder sees every
     * task on their own board; shared boards keep the assignment scope.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('view_all_tasks')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $hasPersonal = $user->hasPermission('create_personal_tasks');
            $hasAssigned = $user->hasPermission('view_assigned_tasks');

            if ($hasPersonal) {
                $visible->whereHas('board', fn (Builder $board) => $board->where('user_id', $user->id));
            }

            if ($hasAssigned) {
                $assigneeScope = fn (Builder $assignees) => $assignees->whereIn(
                    'users.id',
                    app(EmployeeScope::class)->visibleUserIds($user),
                );

                if ($hasPersonal) {
                    $visible->orWhereHas('users', $assigneeScope);
                } else {
                    $visible->whereHas('users', $assigneeScope);
                }
            }

            if (! $hasPersonal && ! $hasAssigned) {
                $visible->whereRaw('1 = 0');
            }
        });
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * The applicant↔contract record this task delivers, when the task was
     * created by a contract assignment.
     */
    public function applicantContract(): BelongsTo
    {
        return $this->belongsTo(ApplicantContract::class, 'applicant_contract_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')->withPivot('contribution_percent');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }
}
