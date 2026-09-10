<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subtask extends Model
{
    /** @use HasFactory<SubtaskFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['task_id', 'user_id', 'title', 'category', 'priority', 'is_completed'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
