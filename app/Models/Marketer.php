<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marketer extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'status', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
