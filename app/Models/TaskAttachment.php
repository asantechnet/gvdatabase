<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskAttachment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['task_id', 'user_id', 'section', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    protected $casts = ['size' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
