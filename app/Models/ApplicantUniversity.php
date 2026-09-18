<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantUniversity extends Model
{
    use Auditable;

    public const STATUSES = ['pending', 'applied', 'admitted', 'rejected', 'withdrawn'];

    protected $fillable = ['applicant_id', 'university_id', 'status', 'intake', 'notes'];
    protected $attributes = ['status' => 'pending'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
