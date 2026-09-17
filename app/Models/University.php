<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class University extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'country_id', 'city', 'website', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function country(): BelongsTo { return $this->belongsTo(Country::class); }
    public function applicants() { return $this->hasMany(Applicant::class); }
}
