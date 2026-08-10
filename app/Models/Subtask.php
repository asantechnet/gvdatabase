<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    /** @use HasFactory<\Database\Factories\SubtaskFactory> */
    use HasFactory;

    protected $fillable = ['task_id', 'title', 'category', 'priority', 'is_completed'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
