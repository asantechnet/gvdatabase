<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    use Auditable, HasFactory;

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
            && $this->longitude !== null;
    }

    public function employees(): HasMany { return $this->hasMany(Employee::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
    public function stocks(): HasMany { return $this->hasMany(Stock::class); }
}
