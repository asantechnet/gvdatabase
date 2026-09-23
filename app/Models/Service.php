<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'type',
        'category',
        'description',
        'fee',
        'status',
        'currency_id',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
    ];

    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /** Contract lines selling this catalogue service. */
    public function contractServices()
    {
        return $this->hasMany(ContractService::class);
    }

    public function contracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_service')
            ->withTimestamps();
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_service')
            ->withPivot(['allocated_fee', 'sort_order'])->withTimestamps();
    }

    /**
     * Delivery projects that include this catalogue service.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withTimestamps();
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)
            ->using(InvoiceService::class)
            ->withPivot([
                'snapshot_name',
                'snapshot_service_fee',
                'snapshot_service_currency_code',
                'snapshot_service_currency_symbol',
                'snapshot_cnv_curr_shrt',
                'snapshot_base_curr_shrt',
                'snapshot_rate',
                'snapshot_converted_fee',
                'employee_id',
                'snapshot_commission_percentage',
                'quantity',
                'line_total',
            ])
            ->withTimestamps();
    }
}
