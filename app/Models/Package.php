<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A package is a sellable bundle of catalogue services.
 *
 * It behaves like a {@see Service} in every contractual sense — it carries its
 * own fee/currency, it can own one contract template, and it is assigned to an
 * applicant case — but it stays its own table so a case, a contract line and a
 * report can distinguish "the bundle that was sold" from "one deliverable
 * inside it".
 *
 * The package fee is *not* the sum of the member fees: the difference is the
 * bundle discount. Each member service carries an `allocated_fee` share of that
 * price in the `package_service` pivot.
 */
class Package extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'packages';

    protected $fillable = ['name', 'code', 'description', 'service_type', 'is_active', 'currency_id', 'fee', 'service_manifest'];

    protected $casts = ['is_active' => 'boolean', 'fee' => 'decimal:6', 'service_manifest' => 'array'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_service')
            ->withPivot(['allocated_fee', 'sort_order'])->withTimestamps()->orderByPivot('sort_order');
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** Every contract template bound to this package (one per version set). */
    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class, 'package_id');
    }

    /** The template the issuance fronts use when no explicit one is given. */
    public function contractTemplate(): HasOne
    {
        return $this->hasOne(ContractTemplate::class, 'package_id')->latestOfMany('id');
    }

    /** The active template that represents this package's agreement, if any. */
    public function primaryContractTemplate(): ?ContractTemplate
    {
        return $this->contractTemplates()->where('is_active', true)->latest('id')->first();
    }

    public function hasContractTemplate(): bool
    {
        return $this->contractTemplates()->where('is_active', true)->exists();
    }

    /**
     * The packing list as plain data: member services with their share of the
     * package price, so callers never reach into the pivot directly.
     *
     * @return list<array{service: Service, allocated_fee: float|null, sort_order: int}>
     */
    public function packingList(): array
    {
        return $this->services->values()->map(fn (Service $service, int $index) => [
            'service' => $service,
            'allocated_fee' => $service->pivot->allocated_fee === null ? null : (float) $service->pivot->allocated_fee,
            'sort_order' => (int) ($service->pivot->sort_order ?? $index),
        ])->all();
    }

    /** Sum of the member fees: what the bundle would cost without the discount. */
    public function totalServiceFee(): float
    {
        return (float) $this->services->sum(fn (Service $service) => (float) ($service->pivot->allocated_fee ?? $service->fee));
    }

    /** How much cheaper the bundle is than buying its services separately. */
    public function discount(): ?float
    {
        return $this->fee === null ? null : round($this->totalServiceFee() - (float) $this->fee, 6);
    }
}
