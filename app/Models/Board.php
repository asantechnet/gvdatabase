<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['title', 'order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_user');
    }
}
