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

    protected static function booted(): void
    {
        // Keep the legacy free-text column in sync so contract placeholders
        // and older readers continue to resolve a department name.
        static::saving(function (Employee $employee): void {
            if (! $employee->department_id || ! $employee->isDirty('department_id')) {
                return;
            }

            $name = Department::query()->whereKey($employee->department_id)->value('name');
            if ($name !== null) {
                $employee->department = $name;
            }
        });

        // A legacy write (the single department select) still works: it
        // attaches or promotes that membership without detaching the others.
        static::saved(function (Employee $employee): void {
            // Eloquent does not populate `changes` for an insert, so the create
            // path needs its own check; otherwise a factory or importer that
            // sets `department_id` would leave the pivot out of step.
            if (! $employee->wasRecentlyCreated && ! $employee->wasChanged('department_id')) {
                return;
            }

            if ($employee->department_id) {
                $now = now();
                DB::table('department_employee')->insertOrIgnore([
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'is_primary' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('department_employee')
                    ->where('employee_id', $employee->id)
                    ->update(['is_primary' => false, 'updated_at' => $now]);
                DB::table('department_employee')
                    ->where('employee_id', $employee->id)
                    ->where('department_id', $employee->department_id)
                    ->update(['is_primary' => true, 'updated_at' => $now]);

                return;
            }

            // Clearing the legacy column promotes the oldest remaining
            // membership, so the pivot and the projection cannot disagree.
            $promoted = DB::table('department_employee')
                ->where('employee_id', $employee->id)
                ->orderBy('created_at')
                ->orderBy('department_id')
                ->value('department_id');

            if ($promoted !== null) {
                $now = now();
                DB::table('department_employee')->where('employee_id', $employee->id)
                    ->update(['is_primary' => false, 'updated_at' => $now]);
                DB::table('department_employee')
                    ->where('employee_id', $employee->id)
                    ->where('department_id', $promoted)
                    ->update(['is_primary' => true, 'updated_at' => $now]);

                $employee->forceFill([
                    'department_id' => $promoted,
                    'department' => Department::query()->whereKey($promoted)->value('name'),
                ])->saveQuietly();
            }
        });
    }

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
        'department',
        'department_id',
        'supervisor_id',
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

    /**
     * The department record. Named `departmentRecord` because the legacy
     * `department` column already occupies the obvious attribute name.
     */
    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Every department this employee belongs to. The primary membership is
     * projected onto `department_id`, so the first row is the one legacy
     * readers see.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_employee')
            ->withPivot('is_primary')
            ->withTimestamps()
            ->orderByDesc('department_employee.is_primary')
            ->orderBy('departments.name');
    }

    /**
     * The only writer for the membership set. It validates the departments,
     * writes the exact set, marks exactly one primary and keeps the legacy
     * `department_id` / `department` columns aligned in the same transaction.
     *
     * @param  array<int, int|string>  $departmentIds
     * @return Collection<int, Department>
     */
    public function syncDepartments(array $departmentIds, ?int $primaryDepartmentId = null): Collection
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

        if ($primaryDepartmentId !== null && ! in_array($primaryDepartmentId, $ids, true)) {
            throw ValidationException::withMessages(['primary_department_id' => ['validation.in']]);
        }

        return DB::transaction(function () use ($ids, $primaryDepartmentId): Collection {
            $currentPrimary = DB::table('department_employee')
                ->where('employee_id', $this->id)
                ->where('is_primary', true)
                ->value('department_id');
            $currentPrimary = $currentPrimary !== null ? (int) $currentPrimary : null;

            $primary = $primaryDepartmentId;
            if ($primary === null && $currentPrimary !== null && in_array($currentPrimary, $ids, true)) {
                $primary = $currentPrimary;
            }
            if ($primary === null && $ids !== []) {
                $primary = (int) (DB::table('department_employee')
                    ->where('employee_id', $this->id)
                    ->whereIn('department_id', $ids)
                    ->orderBy('created_at')
                    ->orderBy('department_id')
                    ->value('department_id') ?? $ids[0]);
            }

            $now = now();
            $remove = DB::table('department_employee')->where('employee_id', $this->id);
            if ($ids === []) {
                $remove->delete();
            } else {
                $remove->whereNotIn('department_id', $ids)->delete();
            }

            foreach ($ids as $id) {
                DB::table('department_employee')->insertOrIgnore([
                    'employee_id' => $this->id,
                    'department_id' => $id,
                    'is_primary' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('department_employee')->where('employee_id', $this->id)
                ->update(['is_primary' => false, 'updated_at' => $now]);
            if ($primary !== null) {
                DB::table('department_employee')
                    ->where('employee_id', $this->id)
                    ->where('department_id', $primary)
                    ->update(['is_primary' => true, 'updated_at' => $now]);
            }

            $this->forceFill([
                'department_id' => $primary,
                'department' => $primary !== null
                    ? Department::query()->whereKey($primary)->value('name')
                    : null,
            ])->saveQuietly();

            $this->unsetRelation('departments');

            return $this->departments()->get();
        });
    }

    /** The team senior/manager this employee reports to. */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /** Employees reporting directly to this employee. */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
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
