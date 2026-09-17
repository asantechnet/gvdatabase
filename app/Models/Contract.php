<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use Auditable, HasDocumentNumber, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'expired', 'renewed', 'terminated', 'cancelled'];

    protected $fillable = [
        'template_id',
        'contract_template_version_id',
        'contract_type_id',
        'employee_id',
        'customer_id',
        'applicant_id',
        'company_id',
        'name',
        'type',
        'cover_text',
        'content',
        'snapshot_data',
        'is_custom',
        'status',
        'start_date',
        'end_date',
        'duration_months',
        'counterparty',
        'counterparty_company',
        'counterparty_email',
        'counterparty_contact',
        'counterparty_address',
        'company_signatory_name',
        'is_cancelled',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_months' => 'integer',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'immutable_datetime',
        'is_custom' => 'boolean',
        'snapshot_data' => 'array',
    ];

    protected function documentNumberColumn(): string
    {
        return 'contract_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'CTR';
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class)->withTrashed();
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class)->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
    public function templateVersion(){ return $this->belongsTo(ContractTemplateVersion::class, 'contract_template_version_id')->withTrashed(); }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /** Optional commission/bonus record for a customer-service contract. */
    public function bonusSource(): MorphOne
    {
        return $this->morphOne(EducationalBonusSource::class, 'source');
    }

    /**
     * Resolved contract-type slug. Falls back to the legacy free-text
     * `type` column for rows created before the contract_types migration.
     */
    public function getTypeSlugAttribute(): ?string
    {
        if ($slug = $this->contractType?->slug) {
            return $slug;
        }

        return match (strtolower((string) ($this->attributes['type'] ?? ''))) {
            'employment' => 'employment',
            'customer' => 'customer',
            'company' => 'company',
            default => null,
        };
    }

    public function getNameAttribute()
    {
        return $this->attributes['name'] ?? $this->template?->name;
    }

    public function getTypeAttribute()
    {
        return $this->contractType?->name ?? $this->attributes['type'] ?? $this->template?->type;
    }
}
