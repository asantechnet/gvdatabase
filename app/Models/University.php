<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class University extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'country_id', 'city', 'website', 'phone', 'email', 'portal_url', 'description', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function interactions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at');
    }

    public function applicants()
    {
        return $this->hasManyThrough(Applicant::class, ScholarshipApplicantDetail::class, 'university_id', 'id', 'id', 'applicant_id')->where('applicants.service', 'scholarship');
    }
}
