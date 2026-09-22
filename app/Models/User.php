<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use Auditable, HasApiTokens, HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    /** @var array<int, string> */
    private array $pendingRoleNames = [];

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->pendingRoleNames !== []) {
                $user->syncRoles($user->pendingRoleNames);
                $user->pendingRoleNames = [];
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'acc_status',
        'account_settings',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'email_verified_at',
        'must_set_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'account_settings' => 'array',
        'two_factor_secret' => 'encrypted',
        'two_factor_confirmed_at' => 'datetime',
        'must_set_password' => 'boolean',
    ];

    public function positions()
    {
        return $this->belongsToMany(Position::class)
            ->using(PositionUser::class)
            ->withPivot('assigned_at', 'ended_at')
            ->withTimestamps();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function applicantCase(): HasOne
    {
        return $this->hasOne(Applicant::class);
    }

    public function marketer(): HasOne
    {
        return $this->hasOne(Marketer::class);
    }

    public function accountDeactivationRequests(): HasMany
    {
        return $this->hasMany(AccountDeactivationRequest::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_user')
            ->using(NotificationUser::class)
            ->withPivot(['is_read', 'read_at', 'deleted_at'])
            ->withTimestamps();
    }

    public function boards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'board_user');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->withPivot('contribution_percent');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->using(RoleUser::class)
            ->withTimestamps();
    }

    /**
     * Backwards-compatible virtual attribute for factories and import paths.
     * Role names belong in the pivot and must never become a users column.
     */
    public function setRolesAttribute(array|string|null $roles): void
    {
        $this->pendingRoleNames = array_values(array_unique(array_filter((array) $roles)));
    }

    /** Reads a user's role names from the user_roles pivot table. */
    public function roleNames(): array
    {
        return $this->roles()->pluck('roles.name')->all();
    }

    /** Replaces the user's roles from the user_roles pivot table. */
    public function syncRoles(array $names): void
    {
        $ids = Role::query()->whereIn('name', $names)->pluck('id')->all();
        $this->roles()->sync($ids);
    }

    public function isSuperAdmin(): bool
    {
        return in_array('super_admin', $this->roleNames(), true);
    }

    /** @return array<int, string> */
    public function permissionNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->orderBy('name')->pluck('name')->all();
        }

        $roleIds = $this->roles()->pluck('roles.id')->all();
        if ($roleIds === []) {
            return [];
        }

        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.role_id', $roleIds)
            ->distinct()
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || in_array($permission, $this->permissionNames(), true);
    }
}
