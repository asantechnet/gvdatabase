<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One course taken by one durable student. */
class AcademyEnrollment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'completed', 'withdrawn', 'cancelled'];

    public const TERMINAL_STATUSES = ['completed', 'withdrawn', 'cancelled'];

    protected $fillable = [
        'student_id', 'applicant_id', 'contract_id', 'applicant_contract_id',
        'class_id', 'program_id', 'started_on',
        'ends_on', 'status', 'completed_on', 'notes',
    ];

    protected $casts = [
        'started_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'completed_on' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ApplicantContract::class, 'applicant_contract_id');
    }

    public function academyClass(): BelongsTo
    {
        return $this->belongsTo(AcademyClass::class, 'class_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

}
