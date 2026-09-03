<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Marketer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'status', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
