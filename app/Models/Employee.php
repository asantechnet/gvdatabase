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
use Illuminate\Support\Facades\DB;

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
        'tax_number',
        'bank_account_number',
        'total_worked_hours',
        'ID_file',
        'assure_file',
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

    public function worthyEmployees(): HasMany
    {
        return $this->hasMany(WorthyEmployee::class);
    }

    public function paidTimeOffs(): HasMany
    {
        return $this->hasMany(PaidTimeOff::class);
    }

    public function commissionServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'employee_service_commissions')
            ->withPivot('percentage')->withTimestamps();
    }

    /** Commission earned from recorded invoice income, in base currency. */
    public function commissionEarnedBase(): float
    {
        if ($this->employment_type !== 'commission') {
            return 0.0;
        }

        return (float) DB::table('employee_service_commissions as commissions')
            ->join('invoice_service as lines', 'lines.service_id', '=', 'commissions.service_id')
            ->join('invoices', 'invoices.id', '=', 'lines.invoice_id')
            ->join('incomes', 'incomes.invoice_id', '=', 'invoices.id')
            ->where('commissions.employee_id', $this->id)
            ->whereNull('incomes.deleted_at')
            ->where('invoices.amount', '>', 0)
            ->selectRaw('COALESCE(SUM(incomes.total_amount * (lines.line_total / invoices.amount) * (commissions.percentage / 100)), 0) as earned')
            ->value('earned');
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
                ->orWhereDoesntHave('user', fn (Builder $userQuery) => $userQuery->whereJsonContains('roles', 'super_admin'));
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
