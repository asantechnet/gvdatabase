<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'package_types';

    protected $fillable = ['name', 'description', 'service_type', 'is_active', 'currency_id', 'fee', 'service_manifest'];

    protected $casts = ['is_active' => 'boolean', 'fee' => 'decimal:6', 'service_manifest' => 'array'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_service')
            ->withPivot(['allocated_fee', 'sort_order'])->withTimestamps()->orderByPivot('sort_order');
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
