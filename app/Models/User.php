<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use Auditable, HasApiTokens, HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

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
        'roles',
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
        'roles' => 'array',
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

    public function accountDeactivationRequests(): HasMany
    {
        return $this->hasMany(AccountDeactivationRequest::class);
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

    /** Supports both the current JSON cast and legacy JSON-string role values. */
    public function roleNames(): array
    {
        $roles = $this->roles;

        for ($attempt = 0; $attempt < 2 && is_string($roles); $attempt++) {
            $roles = json_decode($roles, true);
        }

        return is_array($roles) ? $roles : [];
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

        $roles = $this->roleNames();
        if ($roles === []) {
            return [];
        }

        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.role', $roles)
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
