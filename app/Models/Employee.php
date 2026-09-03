<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'father_name',
        'contact',
        'id_number',
        'guarantor_id_number',
        'guarantor_phone',
        'guarantor_address',
        'guarantor_licence',
        'addr',
        'education_lvl',
        'position_id',
        'employment_type',
        'office_id',
        'salary',
        'bank_account_number',
        'total_worked_hours',
        'department',
        'join_date',
        'contract_duration',
        'avatar',
        'user_id',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function assetAllocations(): HasMany
    {
        return $this->hasMany(AssetAllocation::class);
    }

    public function worthyEmployees(): HasMany
    {
        return $this->hasMany(WorthyEmployee::class);
    }

    public function paidTimeOffs(): HasMany
    {
        return $this->hasMany(PaidTimeOff::class);
    }

    /**
     * Catalogue services this commission-based employee can deliver, together
     * with the rate agreed for each service. Customer-specific responsibility
     * lives on customer_service; this relation deliberately stays reusable.
     */
    public function commissionServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'employee_service_commissions')
            ->withPivot('percentage')
            ->withTimestamps();
    }

    /** Customer services currently assigned to this delivery employee. */
    public function customerServices(): HasMany
    {
        return $this->hasMany(CustomerService::class);
    }

    /** Commission earned from recorded invoice income, in base currency. */
    public function commissionEarnedBase(): float
    {
        if ($this->employment_type !== 'commission') {
            return 0.0;
        }

        $currentBaseCurrencyId = Currency::query()->where('is_base', true)->value('id');

        return (float) $this->commissionAccruals()
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->when($currentBaseCurrencyId, fn (Builder $query, int $id) => $query->where('base_currency_id', $id))
            ->sum('commission_base_amount');
    }

    public function commissionAccruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    public function commissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Employees who can be selected for staff-related records. */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('user_id')
                ->orWhereDoesntHave('user.roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'super_admin'));
        });
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (! $value) {
                    return null;
                }
                $path = parse_url($value, PHP_URL_PATH) ?: $value;

                return str_starts_with($path, '/storage/avatars/') ? $path : $value;
            },
        );
    }

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'total_worked_hours' => 'decimal:2',
            'join_date' => 'date',
        ];
    }
}
