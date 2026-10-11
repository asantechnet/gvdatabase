<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'email', 'phone', 'address', 'status',
        'latitude', 'longitude', 'geofence_radius_meters', 'geofence_enabled',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius_meters' => 'integer',
            'geofence_enabled' => 'boolean',
        ];
    }

    public function hasActiveGeofence(): bool
    {
        return $this->status === 'active'
            && $this->geofence_enabled
            && $this->latitude !== null
            && $this->longitude !== null
            && abs((float) $this->latitude) <= 90
            && abs((float) $this->longitude) <= 180
            && $this->geofence_radius_meters >= 10
            && $this->geofence_radius_meters <= 5000;
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
