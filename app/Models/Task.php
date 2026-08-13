<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory, Auditable;

    protected $fillable = [
        'board_id',
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
        'order'
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
        static::creating(function (Task $task): void {
            if ((int) $task->progress >= 100 && ! $task->completed_at) {
                $task->completed_at = now('UTC');
            }
        });

        static::updating(function (Task $task): void {
            if (! $task->isDirty('progress')) return;
            $previous = (int) $task->getOriginal('progress');
            $next = (int) $task->progress;
            if ($previous < 100 && $next >= 100) $task->completed_at = now('UTC');
            if ($previous >= 100 && $next < 100) {
                $task->reopened_count = (int) $task->reopened_count + 1;
                $task->completed_at = null;
            }
        });
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
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

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }
}
