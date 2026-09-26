<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationalBonusSource extends Model
{
    use Auditable, SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'paid', 'void'];

    protected $fillable = [
        'employee_id',
        'applicant_id',
        'source_type',
        'source_id',
        'source_amount',
        'bonus_amount',
        'status',
        'rule_source',
    ];

    protected $casts = [
        'source_amount' => 'decimal:6',
        'bonus_amount' => 'decimal:6',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

}
