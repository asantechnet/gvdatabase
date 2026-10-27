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
        'consultant_employee_id',
        'contact',
        'email',
        'service',
        'education_level_id',
        'age',
        'grade_score',
        'province_id',
        'english_level_id',
        'acquisition_route_id',
        'reviewed_by_consultant',
        'notes',
        'converted_at',
        'follow_up',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'converted_at' => 'immutable_datetime',
        'follow_up' => 'boolean',
        'reviewed_by_consultant' => 'boolean',
        'age' => 'integer',
        'grade_score' => 'decimal:2',
    ];

    public function interactions(): MorphMany
    {
        return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at');
    }

    /** The consultant who owns this visitor's enquiry. */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'consultant_employee_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function englishLevel(): BelongsTo
    {
        return $this->belongsTo(EnglishLevel::class);
    }

    public function acquisitionRoute(): BelongsTo
    {
        return $this->belongsTo(AcquisitionRoute::class);
    }
}
