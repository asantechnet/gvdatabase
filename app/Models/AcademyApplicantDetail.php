<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The fields an academy applicant has and no other service asks for.
 * The guardian's name is shared identity and lives on `applicants`.
 */
class AcademyApplicantDetail extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['applicant_id', 'program_id', 'time_id', 'duration_id', 'class_id'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function academyClass(): BelongsTo
    {
        return $this->belongsTo(AcademyClass::class, 'class_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class, 'time_id');
    }

    public function duration(): BelongsTo
    {
        return $this->belongsTo(Duration::class);
    }
}
