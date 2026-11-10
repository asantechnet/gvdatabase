<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmInteraction extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['contactable_type', 'contactable_id', 'applicant_id', 'created_by', 'channel', 'title', 'notes', 'contacted_at', 'follow_up_at'];
    protected function casts(): array { return ['contacted_at' => 'immutable_datetime', 'follow_up_at' => 'immutable_datetime']; }
    public function contactable(): MorphTo { return $this->morphTo(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
}
