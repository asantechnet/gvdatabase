<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event', 'table_name', 'auditable_type', 'auditable_id', 'user_id', 'employee_id',
        'subject_type', 'subject_label', 'old_values', 'new_values', 'changed_columns',
        'review_status', 'reviewed_by', 'reviewed_at', 'audited_by', 'audited_at',
        'url', 'method', 'ip_address',
    ];

    /** Memoized guard: the flag column arrives with its own migration. */
    private static ?bool $hasUnreadAuditColumn = null;

    protected static function booted(): void
    {
        // Raise the real-time audit flag for everyone allowed to review audit
        // history. The heartbeat drains it; the flag is a cheap boolean so a
        // bulk write only costs one UPDATE.
        static::created(function (): void {
            // Earlier migrations create audited models (roles, permissions)
            // before the flag column exists. Skipping the write there keeps a
            // fresh install migrating cleanly; once the column exists the check
            // short-circuits and the flag write costs nothing extra.
            if (self::$hasUnreadAuditColumn !== true) {
                self::$hasUnreadAuditColumn = Schema::hasColumn('users', 'unread_audit_logs');
                if (! self::$hasUnreadAuditColumn) {
                    return;
                }
            }

            User::query()
                ->where('acc_status', 'active')
                ->where('unread_audit_logs', false)
                ->where(function ($query): void {
                    $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                        ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->whereIn('name', [
                            'view_audit_logs', 'view_team_audit_logs',
                        ]));
                })
                ->update(['unread_audit_logs' => true]);
        });
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_columns' => 'array',
            'reviewed_at' => 'datetime',
            'audited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by')->withTrashed();
    }
}
