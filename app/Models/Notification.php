<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'notification_key',
        'title',
        'message',
        'data',
        'date_time',
        'employee_id',
        'go_to_url',
    ];

    protected function casts(): array
    {
        return ['date_time' => 'datetime', 'data' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_user')
            ->using(NotificationUser::class)
            ->withPivot(['is_read', 'read_at', 'deleted_at'])
            ->withTimestamps();
    }

    /** @param iterable<int> $userIds */
    public function deliverTo(iterable $userIds): void
    {
        $recipientIds = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $preferenceKey = $this->preferenceKey();

        if ($preferenceKey && $recipientIds->isNotEmpty()) {
            $recipientIds = User::query()
                ->whereIn('id', $recipientIds)
                ->get(['id', 'account_settings'])
                ->filter(fn (User $user) => data_get(
                    $user->account_settings,
                    "notifications.{$preferenceKey}.in_app",
                    true,
                ) !== false)
                ->pluck('id');
        }

        if ($recipientIds->isNotEmpty()) {
            $this->recipients()->syncWithoutDetaching($recipientIds->mapWithKeys(fn ($id) => [$id => [
                'is_read' => null,
                'read_at' => null,
                'deleted_at' => null,
            ]])->all());

            // Raise the real-time flag in the same delivery operation so the
            // heartbeat can pick the rows up without polling the whole inbox.
            // Recipients who opted out of in-app delivery were already filtered
            // out above, so they are never flagged.
            User::query()->whereIn('id', $recipientIds)->update(['unread_notifications' => true]);
        }
    }

    private function preferenceKey(): ?string
    {
        $classification = strtolower($this->type.' '.$this->notification_key);

        return match (true) {
            str_contains($classification, 'audit') => 'audit_activity',
            str_contains($classification, 'finance'),
            str_contains($classification, 'income'),
            str_contains($classification, 'expense'),
            str_contains($classification, 'payment') => 'finance_updates',
            str_contains($classification, 'applicant') => 'applicant_updates',
            str_contains($classification, 'task'),
            str_contains($classification, 'board'),
            str_contains($classification, 'comment'),
            str_contains($classification, 'contract') => 'work_updates',
            str_contains($classification, 'staff'),
            str_contains($classification, 'employee'),
            str_contains($classification, 'attendance'),
            str_contains($classification, 'salary'),
            str_contains($classification, 'bonus') => 'staff_updates',
            default => null,
        };
    }
}
