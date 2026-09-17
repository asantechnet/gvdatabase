<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageType extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'description', 'service_type', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function scholarships(): HasMany
    {
        return $this->hasMany(Scholarship::class);
    }

    public function testOffers(): HasMany
    {
        return $this->hasMany(TestOffer::class);
    }
}
