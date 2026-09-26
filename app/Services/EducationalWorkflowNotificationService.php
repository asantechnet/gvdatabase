<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\CaseWin;
use App\Models\CustomerPackage;
use App\Models\Notification;
use App\Models\PendingTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EducationalWorkflowNotificationService
{
    public function caseWon(CaseWin $win, ?int $actorId = null): void
    {
        $win->loadMissing(['applicant', 'employee']);
        $recipientIds = $this->usersWithPermission('grant_bonuses');
        $employeeUserId = $win->employee?->user_id;
        if ($employeeUserId && (int) $employeeUserId !== $actorId) {
            $recipientIds->push((int) $employeeUserId);
        }
        $this->notifyOnce(
            "case.won.{$win->id}", 'bonus', 'Applicant case won',
            "Applicant case #{$win->applicant_id} was won by {$win->employee->name}.",
            ['translation_key' => 'case.won', 'case_win_id' => $win->id, 'applicant_id' => $win->applicant_id, 'employee' => $win->employee->name],
            "/applicants/{$win->applicant_id}", $recipientIds,
        );
    }

    public function packageIssued(CustomerPackage $purchase, ?int $actorId = null): void
    {
        $purchase->loadMissing(['applicant.responsibleEmployee', 'package']);
        $recipientIds = collect([$purchase->applicant?->responsibleEmployee?->user_id])
            ->filter(fn ($id) => $id && (int) $id !== $actorId);
        $this->notifyOnce(
            "package.issued.{$purchase->id}",
            'task',
            'Package services issued',
            "{$purchase->package->name} was issued with {$purchase->services()->count()} service(s).",
            ['translation_key' => 'package.issued', 'customer_package_id' => $purchase->id, 'package' => $purchase->package->name, 'applicant_id' => $purchase->applicant_id],
            "/applicants/{$purchase->applicant_id}",
            $recipientIds,
        );
    }

    public function applicantAssigned(Applicant $applicant, ?int $actorId = null): void
    {
        $userId = $applicant->responsibleEmployee?->user_id;
        if (! $userId || $userId === $actorId) {
            return;
        }

        $this->notifyOnce(
            "applicant.assigned.{$applicant->id}.{$userId}",
            'task',
            'Applicant case assigned',
            "Applicant case #{$applicant->id} has been assigned to you.",
            ['translation_key' => 'applicant.assigned', 'applicant_id' => $applicant->id, 'applicant' => $this->applicantLabel($applicant)],
            "/applicants/{$applicant->id}",
            collect([$userId]),
        );
    }

    public function applicantStageChanged(Applicant $applicant, ApplicantStage $stage, ApplicantStageHistory $history, ?int $actorId = null): void
    {
        $staffIds = collect([$applicant->responsibleEmployee?->user_id])
            ->filter(fn ($id) => $id && (int) $id !== $actorId);
        $this->notifyOnce(
            "applicant.stage.{$history->id}.staff",
            'task',
            'Applicant stage updated',
            "Applicant case #{$applicant->id} moved to {$stage->name}.",
            [
                'applicant_id' => $applicant->id,
                'applicant' => $this->applicantLabel($applicant),
                'stage' => $stage->name,
                'translation_key' => 'applicant.stageStaff',
            ],
            "/applicants/{$applicant->id}",
            $staffIds,
        );

        $applicantIds = collect([$applicant->user_id])
            ->filter(fn ($id) => $id && (int) $id !== $actorId);
        $this->notifyOnce(
            "applicant.stage.{$history->id}.applicant",
            'task',
            'Your visa case was updated',
            "Your visa case moved to {$stage->name}.",
            [
                'applicant_id' => $applicant->id,
                'applicant' => $this->applicantLabel($applicant),
                'stage' => $stage->name,
                'translation_key' => 'applicant.stageApplicant',
            ],
            '/my-case',
            $applicantIds,
        );
    }

    public function pendingSubmitted(PendingTransaction $pending): void
    {
        $recipientIds = $this->usersWithPermission('approve_pending_transactions')
            ->reject(fn (int $id) => $id === (int) $pending->requested_by);

        $this->notifyOnce(
            "finance.pending.submitted.{$pending->id}",
            'finance',
            'Financial request awaiting review',
            "Pending {$pending->transaction_type} request #{$pending->id} is ready for review.",
            [...$this->pendingData($pending), 'translation_key' => 'finance.pendingSubmitted'],
            '/pending-transactions',
            $recipientIds,
        );
    }

    public function pendingStatusChanged(PendingTransaction $pending, string $status, ?int $actorId = null): void
    {
        $recipientIds = collect([$pending->requested_by])
            ->filter(fn ($id) => $id && (int) $id !== $actorId);

        $this->notifyOnce(
            "finance.pending.{$status}.{$pending->id}",
            $status === PendingTransaction::REJECTED ? 'error' : 'finance',
            'Financial request updated',
            "Pending {$pending->transaction_type} request #{$pending->id} is now ".str_replace('_', ' ', $status).'.',
            [...$this->pendingData($pending), 'status' => $status, 'reason' => $pending->rejection_reason, 'translation_key' => 'finance.pendingStatus'],
            '/pending-transactions',
            $recipientIds,
        );
    }

    public function sendStalledCaseReminders(CarbonImmutable $today): int
    {
        $sent = 0;
        $today = $today->startOfDay();

        Applicant::query()
            ->where('status', 'active')
            ->with(['currentStage:id,name', 'responsibleEmployee.user:id'])
            ->orderBy('id')
            ->each(function (Applicant $applicant) use ($today, &$sent): void {
                $history = $applicant->stageHistory()->reorder()->latest('changed_at')->first();
                $enteredAt = $history?->changed_at;
                if (! $enteredAt) {
                    return;
                }

                $days = (int) CarbonImmutable::instance($enteredAt)->startOfDay()->diffInDays($today);
                $threshold = collect([30, 21, 14])->first(fn (int $value) => $days >= $value);
                if ($threshold === null) {
                    return;
                }

                $recipientIds = collect([$applicant->responsibleEmployee?->user_id])->filter();
                if ($recipientIds->isEmpty()) {
                    $recipientIds = $this->usersWithPermission('view_applicants');
                }

                $created = $this->notifyOnce(
                    "applicant.stalled.{$applicant->id}.{$applicant->current_stage_id}.{$enteredAt->toDateString()}.{$threshold}",
                    $threshold >= 30 ? 'error' : 'warning',
                    'Applicant case needs attention',
                    "Applicant case #{$applicant->id} has remained in {$applicant->currentStage?->name} for {$days} days.",
                    [
                        'applicant_id' => $applicant->id,
                        'applicant' => $this->applicantLabel($applicant),
                        'stage' => $applicant->currentStage?->name,
                        'days' => $days,
                        'translation_key' => 'applicant.stalled',
                    ],
                    "/applicants/{$applicant->id}",
                    $recipientIds,
                );

                if ($created) {
                    $sent++;
                }
            });

        return $sent;
    }

    /** @param Collection<int, int> $recipientIds */
    private function notifyOnce(string $key, string $type, string $title, string $message, array $data, string $url, Collection $recipientIds): bool
    {
        $recipientIds = $recipientIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($recipientIds->isEmpty()) {
            return false;
        }

        $notification = Notification::query()->firstOrCreate(
            ['notification_key' => $key],
            compact('type', 'title', 'message', 'data') + ['date_time' => now(), 'go_to_url' => $url],
        );
        $notification->deliverTo($recipientIds);

        return $notification->wasRecentlyCreated;
    }

    /** @return Collection<int, int> */
    private function usersWithPermission(string $permission): Collection
    {
        return User::query()
            ->where('acc_status', 'active')
            ->where(function ($query) use ($permission): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', $permission));
            })
            ->pluck('id');
    }

    private function applicantLabel(Applicant $applicant): string
    {
        return $applicant->name ?: "Case #{$applicant->id}";
    }

    private function pendingData(PendingTransaction $pending): array
    {
        return [
            'pending_transaction_id' => $pending->id,
            'transaction_type' => $pending->transaction_type,
            'amount' => (string) $pending->amount,
            'requester' => $pending->requester?->name,
        ];
    }
}
