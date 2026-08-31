<?php

namespace App\Services;

use App\Models\AccountDeactivationRequest;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Support\AuditRegistry;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountDeactivationService
{
    public function __construct(private readonly RoleHierarchy $hierarchy) {}

    public function request(User $user, string $password, ?string $reason, Request $httpRequest): AccountDeactivationRequest
    {
        abort_if($user->isSuperAdmin(), 422, 'business.super_admin_deactivation_forbidden');
        abort_unless(Hash::check($password, $user->password), 422, 'business.current_password_invalid');
        abort_unless($user->acc_status === 'active', 422, 'business.account_not_active');

        $deactivation = DB::transaction(function () use ($user, $reason, $httpRequest) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if(
                AccountDeactivationRequest::query()->where('pending_user_id', $lockedUser->id)->exists(),
                409,
                'business.deactivation_request_pending',
            );

            $rank = $this->hierarchy->rank($lockedUser);
            abort_unless($this->eligibleReviewers($lockedUser, $rank)->isNotEmpty(), 422, 'business.no_eligible_deactivation_reviewer');

            $deactivation = AccountDeactivationRequest::create([
                'user_id' => $lockedUser->id,
                'pending_user_id' => $lockedUser->id,
                'employee_id' => $lockedUser->employee()->value('id'),
                'reason' => $reason,
                'status' => AccountDeactivationRequest::STATUS_PENDING,
                'requester_rank' => $rank,
                'requester_roles' => $lockedUser->roleNames(),
                'requested_at' => now(),
                'audit_metadata' => ['events' => [$this->metadata('requested', $httpRequest, $lockedUser)]],
            ]);

            $this->audit('account_deactivation_requested', $deactivation, $lockedUser, [], [
                'status' => $deactivation->status,
                'reason' => $deactivation->reason,
            ]);

            return $deactivation;
        });

        $notification = $this->notification('account_deactivation.requested', $deactivation, [
            'requester' => $user->name,
        ]);
        $notification->deliverTo($this->eligibleReviewers($user, $deactivation->requester_rank)->pluck('id'));

        return $deactivation->load(['user', 'employee', 'reviewer']);
    }

    public function cancel(AccountDeactivationRequest $deactivation, User $actor, Request $httpRequest): AccountDeactivationRequest
    {
        return DB::transaction(function () use ($deactivation, $actor, $httpRequest) {
            $locked = AccountDeactivationRequest::query()->lockForUpdate()->findOrFail($deactivation->id);
            abort_unless($locked->user_id === $actor->id, 403, 'auth.forbidden');
            $this->ensurePending($locked);

            $locked->update([
                'status' => AccountDeactivationRequest::STATUS_CANCELLED,
                'pending_user_id' => null,
                'cancelled_at' => now(),
                'audit_metadata' => $this->appendMetadata($locked, 'cancelled', $httpRequest, $actor),
            ]);
            $this->audit('account_deactivation_cancelled', $locked, $actor, ['status' => 'pending'], ['status' => 'cancelled']);
            $this->notification('account_deactivation.cancelled', $locked, ['requester' => $actor->name])
                ->deliverTo($this->eligibleReviewers($actor, $locked->requester_rank)->pluck('id'));

            return $locked->load(['user', 'employee', 'reviewer']);
        });
    }

    public function review(AccountDeactivationRequest $deactivation, User $reviewer, string $decision, ?string $notes, Request $httpRequest): AccountDeactivationRequest
    {
        return DB::transaction(function () use ($deactivation, $reviewer, $decision, $notes, $httpRequest) {
            $locked = AccountDeactivationRequest::query()->lockForUpdate()->findOrFail($deactivation->id);
            $this->ensurePending($locked);
            $requester = User::withTrashed()->lockForUpdate()->findOrFail($locked->user_id);
            abort_unless($this->hierarchy->canReview($reviewer, $requester, $locked->requester_rank), 403, 'business.reviewer_rank_insufficient');

            $status = $decision === 'approve'
                ? AccountDeactivationRequest::STATUS_APPROVED
                : AccountDeactivationRequest::STATUS_REJECTED;
            $locked->update([
                'status' => $status,
                'pending_user_id' => null,
                'reviewer_id' => $reviewer->id,
                'reviewer_rank' => $this->hierarchy->rank($reviewer),
                'reviewed_at' => now(),
                'decision_notes' => $notes,
                'audit_metadata' => $this->appendMetadata($locked, $status, $httpRequest, $reviewer),
            ]);

            $this->audit("account_deactivation_{$status}", $locked, $reviewer, ['status' => 'pending'], [
                'status' => $status,
                'decision_notes' => $notes,
            ]);

            if ($status === AccountDeactivationRequest::STATUS_APPROVED) {
                User::withoutEvents(function () use ($requester): void {
                    $requester->forceFill(['acc_status' => 'deactivated'])->save();
                });
                $requester->tokens()->delete();
                $this->audit('account_deactivated', $requester, $reviewer, ['acc_status' => 'active'], ['acc_status' => 'deactivated']);
            }

            $this->notification("account_deactivation.{$status}", $locked, [
                'reviewer' => $reviewer->name,
            ])->deliverTo([$requester->id]);

            return $locked->load(['user', 'employee', 'reviewer']);
        });
    }

    public function reactivate(User $user, User $actor, ?string $notes, Request $httpRequest): User
    {
        return DB::transaction(function () use ($user, $actor, $notes, $httpRequest) {
            $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->acc_status === 'deactivated', 422, 'business.account_not_deactivated');
            abort_unless($this->hierarchy->canReview($actor, $locked), 403, 'business.reviewer_rank_insufficient');

            User::withoutEvents(function () use ($locked): void {
                $locked->forceFill(['acc_status' => 'active'])->save();
            });
            $this->audit('account_reactivated', $locked, $actor, ['acc_status' => 'deactivated'], [
                'acc_status' => 'active',
                'decision_notes' => $notes,
                'metadata' => $this->metadata('reactivated', $httpRequest, $actor),
            ]);
            $this->notification('account_deactivation.reactivated', null, [
                'reviewer' => $actor->name,
            ])->deliverTo([$locked->id]);

            return $locked->fresh();
        });
    }

    public function visibleToReviewer(User $reviewer)
    {
        return AccountDeactivationRequest::query()
            ->with(['user', 'employee', 'reviewer'])
            ->latest('requested_at')
            ->limit(200)
            ->get()
            ->filter(fn (AccountDeactivationRequest $item) => $item->user
                && $this->hierarchy->canReview($reviewer, $item->user, $item->requester_rank))
            ->values();
    }

    private function eligibleReviewers(User $requester, int $protectedRank)
    {
        return User::query()
            ->whereKeyNot($requester->id)
            ->where('acc_status', 'active')
            ->get()
            ->filter(fn (User $candidate) => $candidate->hasPermission('review_account_deactivation_requests')
                && $this->hierarchy->canReview($candidate, $requester, $protectedRank));
    }

    private function ensurePending(AccountDeactivationRequest $deactivation): void
    {
        abort_unless($deactivation->status === AccountDeactivationRequest::STATUS_PENDING, 409, 'business.deactivation_request_not_pending');
    }

    private function metadata(string $event, Request $request, User $actor): array
    {
        return [
            'event' => $event,
            'actor_id' => $actor->id,
            'occurred_at' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function appendMetadata(AccountDeactivationRequest $deactivation, string $event, Request $request, User $actor): array
    {
        $metadata = $deactivation->audit_metadata ?? [];
        $metadata['events'] = [...($metadata['events'] ?? []), $this->metadata($event, $request, $actor)];

        return $metadata;
    }

    private function audit(string $event, $subject, User $actor, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'event' => $event,
            'table_name' => $subject->getTable(),
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'subject_type' => $subject instanceof User ? AuditRegistry::keyFor($subject) : 'account_deactivations',
            'subject_label' => $subject instanceof User
                ? AuditRegistry::subjectLabel($subject)
                : (string) ($subject->user?->name ?? $actor->name),
            'user_id' => $actor->id,
            'employee_id' => $actor->employee()->value('id'),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_columns' => array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)])),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip_address' => request()->ip(),
        ]);
    }

    private function notification(string $key, ?AccountDeactivationRequest $deactivation, array $data): Notification
    {
        $isReviewerAction = in_array($key, [
            'account_deactivation.requested',
            'account_deactivation.cancelled',
        ], true);

        return Notification::create([
            'type' => 'account_deactivation',
            'notification_key' => $key,
            'data' => [
                ...$data,
                'deactivation_request_id' => $deactivation?->id,
            ],
            'date_time' => now(),
            'employee_id' => $deactivation?->employee_id,
            'go_to_url' => $isReviewerAction
                ? '/account-settings/deactivation-requests'
                : '/account-settings/account',
        ]);
    }
}
