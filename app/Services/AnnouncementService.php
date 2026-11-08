<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves announcement audiences into concrete user accounts and delivers the
 * announcement into their notification inboxes. The educational system adds an
 * applicants audience reaching active applicant portal accounts. See
 * asantech-mini-management/ANNOUNCEMENTS_FEATURE_PLAN.md.
 */
class AnnouncementService
{
    /** @param array<int, array{type: string, id?: int}> $audience */
    public function announce(Announcement $announcement, array $audience): int
    {
        $recipientIds = $this->recipientIdsFor($audience);

        if ($recipientIds->isEmpty()) {
            return 0;
        }

        $sender = $announcement->sender;

        $notification = Notification::create([
            'type' => 'announcement',
            'notification_key' => "announcement.{$announcement->id}",
            'title' => $announcement->title,
            'message' => $announcement->message,
            'data' => [
                'announcement_id' => $announcement->id,
                'announcement_sender' => $sender?->name,
                'announcement_labels' => $announcement->labels ?? [],
                'announcement_title' => $announcement->title,
            ],
            'date_time' => now(),
            'go_to_url' => '/notifications',
        ]);

        $notification->deliverTo($recipientIds);

        return $recipientIds->count();
    }

    /** Keep the delivered notification aligned when its announcement changes. */
    public function refresh(Announcement $announcement, array $audience): int
    {
        $notification = Notification::query()
            ->where('notification_key', "announcement.{$announcement->id}")
            ->first();

        if (! $notification) {
            return $this->announce($announcement, $audience);
        }

        $notification->update([
            'title' => $announcement->title,
            'message' => $announcement->message,
            'data' => [
                'announcement_id' => $announcement->id,
                'announcement_sender' => $announcement->sender?->name,
                'announcement_labels' => $announcement->labels ?? [],
                'announcement_title' => $announcement->title,
            ],
        ]);

        $recipientIds = $this->recipientIdsFor($audience);
        $existingIds = $notification->recipients()->pluck('users.id');
        $removedIds = $existingIds->diff($recipientIds);
        if ($removedIds->isNotEmpty()) {
            $notification->recipients()->detach($removedIds);
        }

        // Only newly eligible users receive an unread delivery. Existing users
        // retain their read state while seeing the amended notification text.
        $notification->deliverTo($recipientIds->diff($existingIds));

        return $recipientIds->count();
    }

    /** Remove the shared inbox entry when its source announcement is deleted. */
    public function remove(Announcement $announcement): void
    {
        Notification::query()
            ->where('notification_key', "announcement.{$announcement->id}")
            ->each(fn (Notification $notification) => $notification->delete());
    }

    /**
     * Resolution happens at send time: an announcement is delivered to the
     * people matching the audience then, not to whoever matches later.
     *
     * @param  array<int, array{type: string, id?: int}>  $audience
     * @return Collection<int, int>
     */
    public function recipientIdsFor(array $audience): Collection
    {
        $recipientIds = collect();

        foreach ($audience as $scope) {
            $recipientIds = $recipientIds->merge(match ($scope['type']) {
                'all_employees' => $this->allEmployeeUserIds(),
                'position' => $this->positionUserIds((int) ($scope['id'] ?? 0)),
                'role' => $this->roleUserIds((int) ($scope['id'] ?? 0)),
                'department' => $this->departmentUserIds((int) ($scope['id'] ?? 0)),
                'applicants' => $this->applicantUserIds(),
                default => collect(),
            });
        }

        return $recipientIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** Every active account that has an employee profile. */
    private function allEmployeeUserIds(): Collection
    {
        return User::query()
            ->where('acc_status', 'active')
            ->whereHas('employee')
            ->pluck('id');
    }

    /** Active applicant portal accounts (applicants.user_id). */
    private function applicantUserIds(): Collection
    {
        return Applicant::query()
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('acc_status', 'active'))
            ->pluck('user_id');
    }

    private function positionUserIds(int $positionId): Collection
    {
        if ($positionId <= 0 || ! Position::query()->whereKey($positionId)->exists()) {
            return collect();
        }

        return Employee::query()
            ->where('position_id', $positionId)
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('acc_status', 'active'))
            ->pluck('user_id');
    }

    private function departmentUserIds(int $departmentId): Collection
    {
        if ($departmentId <= 0 || ! Department::query()->whereKey($departmentId)->exists()) {
            return collect();
        }

        return Employee::query()
            ->where('department_id', $departmentId)
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($query) => $query->where('acc_status', 'active'))
            ->pluck('user_id');
    }

    private function roleUserIds(int $roleId): Collection
    {
        $role = Role::query()->find($roleId);

        if (! $role) {
            return collect();
        }

        return $role->users()
            ->where('acc_status', 'active')
            ->pluck('users.id');
    }
}
