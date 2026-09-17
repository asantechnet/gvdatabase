<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestOffer extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['customer_id', 'applicant_id', 'employee_id', 'currency_id', 'package_type_id', 'date', 'fee', 'status'];
    protected $casts = ['date' => 'date', 'fee' => 'decimal:6'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function packageType(): BelongsTo { return $this->belongsTo(PackageType::class, 'package_type_id'); }
    public function bonusSource(): MorphOne { return $this->morphOne(EducationalBonusSource::class, 'source'); }
}
