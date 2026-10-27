<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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

    protected static function booted(): void
    {
        // Raise the real-time audit flag for everyone allowed to review audit
        // history. The heartbeat drains it; the flag is a cheap boolean so a
        // bulk write only costs one UPDATE.
        static::created(function (): void {
            try {
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
            } catch (QueryException $e) {
                // The flag column arrives with its own migration, and this hook
                // fires for models that earlier migrations create (roles,
                // permissions) as well as for processes whose schema changes
                // underneath them. While the column is absent there is nobody
                // to flag, so that one failure is expected; anything else is
                // still reported.
                if (! self::isMissingUnreadColumn($e)) {
                    throw $e;
                }
            }
        });
    }

    /**
     * A missing flag column is the only expected failure of the bulk update:
     * migrations run against schemas that predate it.
     */
    private static function isMissingUnreadColumn(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, "Unknown column 'unread_audit_logs'")
            || str_contains($message, 'no such column: unread_audit_logs');
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
