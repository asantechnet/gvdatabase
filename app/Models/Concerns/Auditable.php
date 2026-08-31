<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\User;
use App\Support\AuditRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Adds soft deletion and an immutable change history to a business model. */
trait Auditable
{
    use SoftDeletes;

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditValues()));

        static::updated(function (Model $model): void {
            if ($model->isOwnProfileUpdate()) {
                return;
            }

            $changes = collect($model->getChanges())
                ->except($model->auditExcludedAttributes())
                ->all();

            if ($changes !== []) {
                $old = collect($model->getOriginal())->only(array_keys($changes))->all();
                $model->writeAudit('updated', $old, $changes, true);
            }
        });

        static::deleted(function (Model $model): void {
            if (! $model->isForceDeleting()) {
                $model->writeAudit('deleted', $model->auditValues(), [], true);
            }
        });

        static::restored(fn (Model $model) => $model->writeAudit('restored', [], $model->auditValues(), true));
    }

    public function auditValues(): array
    {
        return collect($this->getAttributes())
            ->except($this->auditExcludedAttributes())
            ->map(fn ($value) => $this->auditSerializableValue($value))
            ->all();
    }

    protected function auditExcludedAttributes(): array
    {
        return [
            'id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token',
            'two_factor_secret', 'mail_password', 'calculation_hash',
        ];
    }

    /** Do not audit a user or employee updating their own profile. */
    private function isOwnProfileUpdate(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $userId = (int) auth()->id();

        return ($this instanceof User && (int) $this->getKey() === $userId)
            || ($this instanceof Employee && (int) $this->user_id === $userId);
    }

    private function auditSerializableValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function writeAudit(string $event, array $oldValues, array $newValues, bool $notify = false): void
    {
        if (! AuditRegistry::enabled($this)) {
            return;
        }

        $request = app()->bound('request') ? request() : null;
        $audit = AuditLog::create([
            'event' => $event,
            'table_name' => $this->getTable(),
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'subject_type' => AuditRegistry::keyFor($this),
            'subject_label' => AuditRegistry::subjectLabel($this),
            'user_id' => auth()->id(),
            'employee_id' => auth()->check()
                ? Employee::withTrashed()->where('user_id', auth()->id())->value('id')
                : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_columns' => array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)])),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'ip_address' => $request?->ip(),
        ]);

        if ($notify && auth()->check()) {
            $notification = Notification::create([
                'type' => 'audit',
                'notification_key' => "audit.{$event}",
                'data' => [
                    'audit_log_id' => $audit->id,
                    'resource' => $audit->subject_type,
                    'record' => $audit->subject_label,
                ],
                'date_time' => now(),
                'go_to_url' => '/audits?audit='.$audit->id,
            ]);

            $recipients = User::query()
                ->where('acc_status', 'active')
                ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'super_admin'))
                ->get()
                ->filter(fn (User $user) => data_get($user->account_settings, 'notifications.audit_activity.in_app', true))
                ->pluck('id');

            $notification->deliverTo($recipients);
        }
    }
}
