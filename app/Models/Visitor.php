<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Visitor extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'company',
        'contact',
        'email',
        'service',
        'marketer',
        'marketer_id',
        'notes',
        'converted_at',
        'date',
    ];

    protected $casts = [
        'date' => 'date', 'converted_at' => 'immutable_datetime',
    ];

    public function interactions(): MorphMany { return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at'); }

    public function marketerEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'marketer_id'); }
}
