<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'code', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function universities() { return $this->hasMany(University::class); }
    public function applicants() { return $this->hasMany(Applicant::class); }
}
