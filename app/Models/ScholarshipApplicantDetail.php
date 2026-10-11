<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The fields a scholarship applicant has and no other service asks for.
 * Money lives on the applicant's `applicant_contract` line, except
 * `university_fee`, which is the university's quoted reference cost.
 */
class ScholarshipApplicantDetail extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'applicant_id',
        'passport_id',
        'grade_score',
        'country_id',
        'contract_type_id',
        'education_level_id',
        'semester_id',
        'university_id',
        'university_fee',
        'scholarship_id',
    ];

    protected $casts = [
        'grade_score' => 'decimal:2',
        'university_fee' => 'decimal:6',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(EducationalScholarship::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
