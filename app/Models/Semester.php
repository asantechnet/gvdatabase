<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semester extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'code', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    public function applicants()
    {
        return $this->hasManyThrough(Applicant::class, ScholarshipApplicantDetail::class, 'semester_id', 'id', 'id', 'applicant_id')->where('applicants.service', 'scholarship');
    }
}
