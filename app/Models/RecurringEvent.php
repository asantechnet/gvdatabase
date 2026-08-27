<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringEvent extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name', 'description', 'event_type', 'status', 'frequency', 'interval', 'start_date', 'next_occurrence_date', 'last_occurrence_date', 'end_date', 'payload'];

    protected $casts = ['start_date' => 'date', 'next_occurrence_date' => 'date', 'last_occurrence_date' => 'date', 'end_date' => 'date', 'payload' => 'array', 'interval' => 'integer'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function occurrences(): HasMany { return $this->hasMany(RecurringEventOccurrence::class); }
}
