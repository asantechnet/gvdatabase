<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, HasFactory, SoftDeletes;

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

    /** Contracted service lines of this customer (through its contracts). */
    public function contractServices()
    {
        return $this->hasManyThrough(ContractService::class, Contract::class);
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

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function customerPackages()
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }
}
