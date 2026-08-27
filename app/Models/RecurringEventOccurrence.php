<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecurringEventOccurrence extends Model
{
    use HasFactory;

    protected $fillable = ['recurring_event_id', 'occurrence_date', 'status', 'notification_id', 'notified_at', 'completed_at', 'skipped_at', 'result_type', 'result_id'];
    protected $casts = ['occurrence_date' => 'date', 'notified_at' => 'datetime', 'completed_at' => 'datetime', 'skipped_at' => 'datetime'];

    public function recurringEvent(): BelongsTo { return $this->belongsTo(RecurringEvent::class); }
    public function result(): MorphTo { return $this->morphTo(); }
    public function notification(): BelongsTo { return $this->belongsTo(Notification::class); }
}
