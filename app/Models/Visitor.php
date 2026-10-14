<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'counselor_employee_id',
        'contact',
        'email',
        'service',
        'notes',
        'converted_at',
        'follow_up',
        'date',
    ];

    protected $casts = [
        'date' => 'date', 'converted_at' => 'immutable_datetime', 'follow_up' => 'boolean',
    ];

    public function interactions(): MorphMany
    {
        return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'counselor_employee_id');
    }
}
