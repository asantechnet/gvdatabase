<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'code', 'phone', 'email', 'portal_url', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function universities()
    {
        return $this->hasMany(University::class);
    }

    public function applicants()
    {
        return $this->hasManyThrough(Applicant::class, ScholarshipApplicantDetail::class, 'country_id', 'id', 'id', 'applicant_id')->where('applicants.service', 'scholarship');
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }
}
