<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPackage extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['applicant_id', 'package_id', 'sold_by_employee_id', 'currency_id', 'fee', 'purchased_at', 'status', 'issuance_key', 'notes'];

    protected $casts = ['purchased_at' => 'date', 'fee' => 'decimal:6'];


    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function soldByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sold_by_employee_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ContractService::class);
    }
}
