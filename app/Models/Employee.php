<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ExcludesPrimaryRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Employee extends Model
{
    use Auditable, ExcludesPrimaryRecord, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'father_name',
        'contact',
        'emergency_contact_name',
        'emergency_contact_phone',
        'id_number',
        'guarantor_name',
        'guarantor_id_number',
        'guarantor_phone',
        'guarantor_address',
        'guarantor_licence',
        'addr',
        'education_lvl',
        'position_id',
        'status',
        'office_id',
        'salary',
        'bank_account_number',
        'total_worked_hours',
        'join_date',
        'contract_duration',
        'off_day_allowance_days',
        'is_commission_based',
        'avatar',
        'document_path', 'document_name', 'document_mime', 'document_size',
        'user_id',
    ];

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

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

    /** Per-service commission rates this employee earns when commission-based. */
    public function serviceCommissions(): HasMany
    {
        return $this->hasMany(EmployeeServiceCommission::class);
    }

    /** Commission accruals earned by this employee. */
    public function commissionAccruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** Contracted services sold by this employee. */
    public function soldApplicantContracts(): HasMany
    {
        return $this->hasMany(ApplicantContract::class, 'sold_by_employee_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** Every department this employee belongs to; memberships are equal. */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_employee')
            ->withTimestamps()
            ->orderBy('departments.name');
    }

    /**
     * The only writer for the membership set. It validates the departments and
     * writes the exact set; no membership is treated as primary.
     *
     * @param  array<int, int|string>  $departmentIds
     * @return Collection<int, Department>
     */
    public function syncDepartments(array $departmentIds): Collection
    {
        $ids = collect($departmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $known = Department::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($ids, $known) !== []) {
            throw ValidationException::withMessages(['department_ids' => ['validation.exists']]);
        }

        return DB::transaction(function () use ($ids): Collection {
            $remove = DB::table('department_employee')->where('employee_id', $this->id);
            if ($ids === []) {
                $remove->delete();
            } else {
                $remove->whereNotIn('department_id', $ids)->delete();
            }

            $now = now();
            foreach ($ids as $id) {
                DB::table('department_employee')->insertOrIgnore([
                    'employee_id' => $this->id,
                    'department_id' => $id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->unsetRelation('departments');

            return $this->departments()->get();
        });
    }

    /** Contract/print readers still resolve the employee's department label. */
    protected function department(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $names = $this->relationLoaded('departments')
                    ? $this->departments->pluck('name')
                    : $this->departments()->pluck('departments.name');

                return $names->filter()->join(', ') ?: null;
            },
        );
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Employees that are currently part of the workforce. Every GET endpoint
     * that returns an employee directory or picker applies this scope so
     * inactive employees are never sent to clients by default; management
     * surfaces opt in explicitly (`GET /employees?status=inactive|all`).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
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
            'off_day_allowance_days' => 'decimal:1',
            'join_date' => 'date',
            'is_commission_based' => 'boolean',
        ];
    }
}
