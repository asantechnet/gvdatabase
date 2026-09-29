<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One service line of a customer contract.
 *
 * Successor of the `customer_service` row: carries the same per-customer
 * economics (fee override, currency, attribution, outsourcing, case
 * dimensions, notes) but hangs off a contract instead of living standalone.
 * No deadline / status / sold_at here by design: line lifecycle follows the
 * parent contract (status + start_date live on `contracts`).
 */
class ContractService extends Pivot
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'contract_service';

    public $incrementing = true;

    protected $fillable = [
        'contract_id',
        'service_id',
        'service_variant_id',
        'fee',
        'currency_id',
        'applicant_id',
        'customer_package_id',
        'sold_by_employee_id',
        'marketer_id',
        'outsourcing_company_id',
        'outsourcing_percentage',
        'country_id',
        'education_level_id',
        'language_id',
        'notes',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'outsourcing_percentage' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function soldByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sold_by_employee_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marketer_id');
    }

    public function outsourcingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'outsourcing_company_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** Commission employees credited for delivering this contracted service. */
    public function deliveryEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'contract_service_employee', 'contract_service_id', 'employee_id')
            ->withPivot('percentage')
            ->withTimestamps();
    }

    public function bonusSource()
    {
        return $this->morphOne(EducationalBonusSource::class, 'source');
    }
}
