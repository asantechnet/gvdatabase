<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The fields a test-offer applicant has and no other service asks for. */
class TestApplicantDetail extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['applicant_id', 'passport_id', 'test_id'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }
}
