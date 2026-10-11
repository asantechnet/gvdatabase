<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationLevel extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function applicants(): HasManyThrough
    {
        return $this->hasManyThrough(Applicant::class, ScholarshipApplicantDetail::class, 'education_level_id', 'id', 'id', 'applicant_id')->where('applicants.service', 'scholarship');
    }
}
