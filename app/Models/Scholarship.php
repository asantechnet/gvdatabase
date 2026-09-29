<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scholarship extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['applicant_id', 'country_id', 'education_level_id', 'language_id', 'employee_id', 'currency_id', 'package_id', 'date', 'fee', 'status'];
    protected $casts = ['date' => 'date:Y-m-d', 'fee' => 'decimal:6'];

    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function country(): BelongsTo { return $this->belongsTo(Country::class); }
    public function educationLevel(): BelongsTo { return $this->belongsTo(EducationLevel::class); }
    public function language(): BelongsTo { return $this->belongsTo(Language::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }

    /** The package this sale belongs to, when it was sold as a bundle. */
    public function package(): BelongsTo { return $this->belongsTo(Package::class, 'package_id'); }

    /** @deprecated "Package type" is the old name for a package. */
    public function packageType(): BelongsTo { return $this->package(); }
    public function bonusSource(): MorphOne { return $this->morphOne(EducationalBonusSource::class, 'source'); }
}
