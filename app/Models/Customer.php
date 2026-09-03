<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'company',
        'contact',
        'email',
        'address',
        'tax_number',
        'marketer',
        'marketer_id',
        'notes',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class)
            ->using(CustomerService::class)
            ->wherePivotNull('deleted_at')
            ->withPivot('id', 'deadline')
            ->withTimestamps();
    }

    public function customerServices()
    {
        return $this->hasMany(CustomerService::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /** The employee responsible for acquiring this customer. */
    public function marketerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marketer_id');
    }

    public function interactions(): MorphMany
    {
        return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
