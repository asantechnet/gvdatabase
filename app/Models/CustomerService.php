<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerService extends Pivot
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'customer_service';

    public $incrementing = true;

    protected $fillable = [
        'customer_id',
        'applicant_id',
        'service_id',
        'customer_package_id',
        'employee_id',
        'sold_by_employee_id',
        'marketer_id',
        'outsourcing_company_id',
        'outsourcing_percentage',
        'fee',
        'currency_id',
        'deadline',
        'status',
        'notes',
        'country_id',
        'education_level_id',
        'language_id',
        'sold_at',
        'legacy_source_type',
        'legacy_source_id',
    ];

    protected $casts = [
        'deadline' => 'date',
        'fee' => 'decimal:2',
        'outsourcing_percentage' => 'decimal:2',
        'sold_at' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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

    public function bonusSource()
    {
        return $this->morphOne(EducationalBonusSource::class, 'source');
    }

    /** The employee responsible for delivering this customer's service. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** All commission employees credited for delivering this service. */
    public function deliveryEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_customer_service_commissions', 'customer_service_id', 'employee_id')
            ->withPivot('percentage')
            ->withTimestamps();
    }

    /** The employee credited with winning this customer's service. */
    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marketer_id');
    }

    /** External company contractually responsible for delivering this service. */
    public function outsourcingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'outsourcing_company_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
