<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ExcludesPrimaryRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    }

    protected $fillable = [
        'name',
        'father_name',
        'contact',
        'emergency_contact_name',
        'emergency_contact_phone',
        'id_number',
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
        'paid_leave_days',
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

    public function paidTimeOffs(): HasMany
    {
        return $this->hasMany(PaidTimeOff::class);
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
            'paid_leave_days' => 'decimal:1',
            'join_date' => 'date',
            'is_commission_based' => 'boolean',
        ];
    }
}
