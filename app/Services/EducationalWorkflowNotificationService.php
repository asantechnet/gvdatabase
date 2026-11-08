<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantAttachment;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\CaseWin;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Support\BuiltInService;
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

    /**
     * A contract was generated automatically from a service assignment. Every
     * responsible employee receives exactly one contract notification.
     * Assignment notifications intentionally include the actor when they
     * assigned the contract to themselves.
     */
    public function contractGenerated(Contract $contract, ?int $actorId = null): void
    {
        $contract->loadMissing(['applicant.responsibleEmployees', 'parentContract']);
        $recipientIds = $contract->applicant?->responsibleEmployees->pluck('user_id')->filter()->unique() ?? collect();

        $serviceName = BuiltInService::label($contract->service);
        $isExtension = $contract->contract_role === Contract::ROLE_EXTENSION;

        $this->notifyOnce(
            "contract.generated.{$contract->id}",
            'task',
            $isExtension ? 'Extension contract created' : 'Contract created for service',
            $isExtension
                ? "An extension contract ({$contract->contract_number}) was created for {$serviceName}."
                : "Contract {$contract->contract_number} was created for {$serviceName}.",
            [
                'translation_key' => $isExtension ? 'contract.extensionGenerated' : 'contract.generated',
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'service' => $contract->service,
                'applicant_id' => $contract->applicant_id,
                'parent_contract_number' => $contract->parentContract?->contract_number,
            ],
            '/contracts/'.str_replace('_', '-', $contract->service).'?contractId='.$contract->id,
            $recipientIds,
        );
    }

    /** Notify every responsible employee after a case responsibility edit. */
    public function responsibilityAssigned(Applicant $applicant): void
    {
        $applicant->loadMissing('responsibleEmployees');
        $assignmentStamp = $applicant->updated_at?->format('YmdHisv') ?? now()->format('YmdHisv');

        foreach ($applicant->responsibleEmployees as $employee) {
            if (! $employee->user_id) {
                continue;
            }

            $this->notifyOnce(
                "applicant.responsibility.assigned.{$applicant->id}.{$employee->id}.{$assignmentStamp}",
                'task',
                'Applicant responsibility assigned',
                "Applicant case #{$applicant->id} was assigned to you.",
                [
                    'translation_key' => 'applicant.admissionAssigned',
                    'applicant_id' => $applicant->id,
                    'applicant' => $this->applicantLabel($applicant),
                    'responsible_employee_id' => $employee->id,
                ],
                "/applicants/{$applicant->id}",
                collect([$employee->user_id]),
            );
        }
    }

    public function applicantAssigned(Applicant $applicant, ?int $actorId = null): void
    {
        $userId = $applicant->responsibleEmployee?->user_id;
        if (! $userId || $userId === $actorId) {
            return;
        }

        // The assignee's team senior/manager follows the case too, so a
        // read-only supervisor is aware of the workload without being able to
        // change it.
        $recipients = collect([$userId])
            ->merge($this->supervisorUserIds($applicant->responsibleEmployee))
            ->filter(fn ($id) => (int) $id !== (int) $actorId)
            ->unique();

        $this->notifyOnce(
            "applicant.assigned.{$applicant->id}.{$userId}",
            'task',
            'Applicant case assigned',
            "Applicant case #{$applicant->id} has been assigned to you.",
            ['translation_key' => 'applicant.assigned', 'applicant_id' => $applicant->id, 'applicant' => $this->applicantLabel($applicant)],
            "/applicants/{$applicant->id}",
            $recipients,
        );
    }

    public function applicantDocumentUploaded(Applicant $applicant, ApplicantAttachment $attachment, ?int $actorId = null): void
    {
        $applicant->loadMissing('responsibleEmployee');
        $recipients = collect([$applicant->responsibleEmployee?->user_id])->filter(fn ($id) => $id && (int) $id !== $actorId);
        $this->notifyOnce("applicant.document.{$attachment->id}", 'task', 'Applicant document uploaded', "{$attachment->label} was uploaded for applicant case #{$applicant->id}.", ['translation_key' => 'applicant.documentUploaded', 'applicant_id' => $applicant->id, 'attachment_id' => $attachment->id, 'label' => $attachment->label], "/applicants/{$applicant->id}", $recipients);
    }

    /**
     * A receipt was recorded for an applicant. The case owner is told which
     * receipt was minted so they can print or follow up on the payment.
     */
    public function incomeRecorded(Income $income, ?int $actorId = null): void
    {
        $income->loadMissing(['applicant.responsibleEmployee', 'incomeCurrency']);
        $recipients = collect([$income->applicant?->responsibleEmployee?->user_id])
            ->filter(fn ($id) => $id && (int) $id !== $actorId);

        $amount = number_format((float) $income->amount * (int) $income->quantity, 2)
            .' '.($income->incomeCurrency?->code ?? '');
        // An income may be recorded without an applicant (a walk-in payment),
        // so the label must not assume one exists.
        $payer = $income->applicant ? $this->applicantLabel($income->applicant) : 'the payer';

        $this->notifyOnce(
            "income.recorded.{$income->id}",
            'finance',
            'Payment receipt recorded',
            "Receipt {$income->receipt_number} was recorded for {$payer} ({$amount}).",
            [
                'translation_key' => 'income.recorded',
                'income_id' => $income->id,
                'receipt_number' => $income->receipt_number,
                'applicant_id' => $income->applicant_id,
                'applicant' => $income->applicant ? $this->applicantLabel($income->applicant) : null,
                'amount' => $amount,
            ],
            '/income',
            $recipients,
        );
    }

    /**
     * A task was assigned to one or more members. Each assignee gets their own
     * notification; the key is per task and user so re-saving the assignee list
     * never re-notifies the same person.
     *
     * @param  array<int|string>  $assigneeIds
     */
    public function taskAssigned(Task $task, array $assigneeIds, ?int $actorId = null): void
    {
        $recipients = collect($assigneeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $actorId)
            ->unique()
            ->values();

        foreach ($recipients as $userId) {
            $this->notifyOnce(
                "task.assigned.{$task->id}.{$userId}",
                'task',
                'Task assigned',
                "Task \"{$task->title}\" was assigned to you.",
                [
                    'translation_key' => 'task.assigned',
                    'task_id' => $task->id,
                    'task' => $task->title,
                ],
                '/tasks',
                collect([$userId]),
            );
        }
    }

    public function applicantStageChanged(Applicant $applicant, ApplicantStage $stage, ApplicantStageHistory $history, ?int $actorId = null): void
    {
        $history->loadMissing(['oldStage:id,name', 'newStage:id,name', 'changer:id,name']);
        $from = $history->oldStage?->name ?? 'the previous stage';
        $to = $history->newStage?->name ?? $stage->name;
        $actor = $history->changer?->name
            ?? ($actorId ? User::query()->find($actorId)?->name : null)
            ?? 'System';
        $label = $this->applicantLabel($applicant);

        $staffIds = collect([$applicant->responsibleEmployee?->user_id])
            ->merge($this->supervisorUserIds($applicant->responsibleEmployee))
            ->filter(fn ($id) => $id && (int) $id !== $actorId)
            ->unique();
        $this->notifyOnce(
            "applicant.stage.{$history->id}.staff",
            'task',
            'Applicant stage updated',
            "Applicant case {$label} moved from {$from} to {$to} by {$actor}.",
            [
                'applicant_id' => $applicant->id,
                'applicant' => $label,
                'stage' => $to,
                'from_stage' => $from,
                'to_stage' => $to,
                'actor' => $actor,
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
            "Your visa case moved from {$from} to {$to} by {$actor}.",
            [
                'applicant_id' => $applicant->id,
                'applicant' => $label,
                'stage' => $to,
                'from_stage' => $from,
                'to_stage' => $to,
                'actor' => $actor,
                'translation_key' => 'applicant.stageApplicant',
            ],
            '/my-case',
            $applicantIds,
        );
    }

    /** A draft income or expense is waiting for a finance decision. */
    public function financeDraftSubmitted(Income|Expense $record): void
    {
        $record->loadMissing(['applicant', 'recorder']);
        $recipientIds = $this->usersWithPermission('approve_draft_finance_records')
            ->reject(fn (int $id) => $id === (int) $record->recorded_by);
        $kind = $record instanceof Income ? 'income' : 'expense';

        $this->notifyOnce(
            "finance.draft.submitted.{$kind}.{$record->id}",
            'finance',
            'Draft finance record awaiting approval',
            "Draft {$kind} #{$record->id} is ready for review.",
            [...$this->financeDraftData($record, $kind), 'translation_key' => 'finance.draftSubmitted'],
            $kind === 'income' ? '/income' : '/expenses',
            $recipientIds,
        );
    }

    public function financeDraftStatusChanged(Income|Expense $record, string $status, ?int $actorId = null): void
    {
        $record->loadMissing(['applicant', 'recorder']);
        $recipientIds = collect([$record->recorded_by])
            ->filter(fn ($id) => $id && (int) $id !== $actorId);
        $kind = $record instanceof Income ? 'income' : 'expense';

        $this->notifyOnce(
            "finance.draft.{$status}.{$kind}.{$record->id}",
            $status === Income::REJECTED ? 'error' : 'finance',
            'Draft finance record updated',
            "Draft {$kind} #{$record->id} is now {$status}.",
            [...$this->financeDraftData($record, $kind), 'status' => $status, 'reason' => $record->rejection_reason, 'translation_key' => 'finance.draftStatus'],
            $kind === 'income' ? '/income' : '/expenses',
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

                $recipientIds = collect([$applicant->responsibleEmployee?->user_id])
                    ->merge($this->supervisorUserIds($applicant->responsibleEmployee))
                    ->filter()
                    ->unique();
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
        if ($notification->wasRecentlyCreated) {
            $notification->deliverTo($recipientIds);
        }

        return $notification->wasRecentlyCreated;
    }

    /**
     * Team seniors and department managers who may read (but not necessarily
     * manage) the employee's records. Only users actually holding the view
     * scope are returned, so a notification never links to a forbidden page.
     *
     * @return Collection<int, int>
     */
    private function supervisorUserIds(?Employee $employee): Collection
    {
        if (! $employee) {
            return collect();
        }

        $candidateIds = collect([$employee->supervisor?->user_id]);

        $managerUserId = $employee->department_id
            ? $employee->departmentRecord?->manager?->user_id
            : null;
        $candidateIds->push($managerUserId);

        $candidateIds = $candidateIds->filter()->map(fn ($id) => (int) $id)->unique();
        if ($candidateIds->isEmpty()) {
            return $candidateIds->values();
        }

        return User::query()
            ->whereIn('id', $candidateIds)
            ->where('acc_status', 'active')
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', 'view_subordinate_records'));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
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

    private function financeDraftData(Income|Expense $record, string $kind): array
    {
        return [
            'record_type' => $kind,
            'record_id' => $record->id,
            'amount' => (string) $record->amount,
            'applicant' => $record->applicant ? $this->applicantLabel($record->applicant) : null,
            'recorded_by' => $record->recorder?->name,
        ];
    }
}
