<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use App\Services\CommissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use Auditable, HasDocumentNumber, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'completed', 'terminated', 'cancelled'];

    /** The first contract of a (customer, service) pair. */
    public const ROLE_PRIMARY = 'primary';

    /** A later sale of the same service, linked back at the primary contract. */
    public const ROLE_EXTENSION = 'extension';

    protected static function booted(): void
    {
        // Commission is normally earned by the *case*: a service line pays out
        // once its applicant reaches the terminal `completed` stage. Academy is
        // the exception and accrues from its live agreement (see
        // `CommissionService`). The hooks live on the model so every path — the
        // service-contract flow, extension contracts, a manual status change —
        // re-checks the case and either accrues the new line or voids what no
        // longer earns. `syncForContract` is idempotent.
        $sync = fn (Contract $contract) => app(CommissionService::class)->syncForContract($contract);

        static::created(function (Contract $contract) use ($sync): void {
            $sync($contract);
        });

        static::updated(function (Contract $contract) use ($sync): void {
            if ($contract->wasChanged('status') || $contract->wasChanged('is_cancelled')) {
                $sync($contract);
            }
        });
    }

    protected $fillable = [
        'template_id',
        'service',
        'contract_role',
        'parent_contract_id',
        'contract_template_version_id',
        'contract_country_id',
        'contract_language_id',
        'contract_type_id',
        'employee_id',
        'sold_by_employee_id',
        'applicant_id',
        'name',
        'type',
        'cover_text',
        'content',
        'snapshot_data',
        'is_custom',
        'status',
        'start_date',
        'end_date',
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

    /** Contracts use the compact, customer-facing CTR-26-001 format. */
    protected function documentNumberFormat(): string
    {
        return '%s-%s-%03d';
    }

    protected function documentNumberYearFormat(): string
    {
        return 'y';
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class)->withTrashed();
    }

    /** For an extension contract, the original agreement it extends. */
    public function parentContract()
    {
        return $this->belongsTo(self::class, 'parent_contract_id')->withTrashed();
    }

    /** Extension contracts sold on top of this one. */
    public function extensions()
    {
        return $this->hasMany(self::class, 'parent_contract_id');
    }

    public function isExtension(): bool
    {
        return $this->contract_role === self::ROLE_EXTENSION;
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class)->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class)->withTrashed();
    }

    /**
     * Contracted services (multi-select). Each line carries its own
     * per-customer fee, currency, attribution and case dimensions.
     */
    public function applicantContracts()
    {
        return $this->hasMany(ApplicantContract::class);
    }

    public function soldByEmployee()
    {
        return $this->belongsTo(Employee::class, 'sold_by_employee_id')->withTrashed();
    }

    /** Commission accruals earned from completing this contract. */
    public function commissionAccruals()
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    public function templateVersion()
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'contract_template_version_id')->withTrashed();
    }

    public function contractCountry()
    {
        return $this->belongsTo(Country::class, 'contract_country_id')->withTrashed();
    }

    public function contractLanguage()
    {
        return $this->belongsTo(Language::class, 'contract_language_id')->withTrashed();
    }

    /** Optional bonus record for a customer-service contract. */
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
            'other' => 'other',
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
