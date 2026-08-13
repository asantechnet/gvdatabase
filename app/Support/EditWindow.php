<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EditWindow
{
    public const MINUTES = 60;

    public function editableUntil(Model $record): Carbon
    {
        return $record->created_at->copy()->utc()->addMinutes(self::MINUTES);
    }

    public function isOpen(Model $record): bool
    {
        return now('UTC')->lessThanOrEqualTo($this->editableUntil($record));
    }

    public function canEdit(User $user, Model $record): bool
    {
        return $this->isOpen($record) || $user->hasPermission('override_edit_window');
    }

    public function enforce(User $user, Model $record): void
    {
        if ($this->isOpen($record)) return;
        abort_unless($user->hasPermission('override_edit_window'), 422, 'business.edit_window_expired');

        AuditLog::create([
            'event' => 'edit_window_overridden',
            'table_name' => $record->getTable(),
            'auditable_type' => $record::class,
            'auditable_id' => $record->getKey(),
            'user_id' => $user->id,
            'employee_id' => $user->employee()->value('id'),
            'old_values' => ['editable_until' => $this->editableUntil($record)->toISOString()],
            'new_values' => ['override_used_at' => now('UTC')->toISOString()],
            'changed_columns' => ['edit_window_override'],
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip_address' => request()->ip(),
        ]);
    }
}
