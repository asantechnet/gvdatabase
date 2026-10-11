<?php

namespace App\Services;

use App\Models\ApplicantContract;
use App\Models\Task;
use App\Models\User;
use App\Support\PaymentStatus;
use App\Support\ServiceBoard;

/**
 * Turns a contract assignment into deliverable work.
 *
 * When a contract is created for an applicant, each contracted service line
 * gets exactly one task bound to that line, so:
 *
 *   - the assignee sees what has to be delivered and for whom,
 *   - the applicant's screen can list the work per contracted service,
 *   - re-saving the same contract cannot spawn duplicate work (creation is
 *     idempotent per open line).
 *
 * The board is created on demand so a fresh installation does not need seeded
 * boards for this to work, exactly like the applicant stage automation.
 */
class ContractTaskService
{
    public function __construct(
        private readonly EducationalWorkflowNotificationService $notifications,
    ) {}

    /**
     * Ensure one open delivery task exists for this applicant-contract line.
     *
     * Returns the created task, or null when none was needed (no applicant, or
     * an open task already exists for the line).
     */
    public function ensureForApplicantContract(ApplicantContract $line, ?User $actor = null): ?Task
    {
        $line->loadMissing(['currency', 'contract', 'applicant']);
        $applicant = $line->applicant;
        if (! $applicant) {
            return null;
        }

        // Idempotent across a contract re-save: the service/contract pair is what
        // the work is about, and re-saving replaces the line row (so the old line
        // id must still count as "already handled", even though it is soft
        // deleted). Otherwise every edit would spawn another task for the same
        // deliverable.
        $alreadyOpen = Task::query()
            ->where('applicant_id', $applicant->id)
            ->where('progress', '<', 100)
            ->where(function ($query) use ($line): void {
                $query->where('applicant_contract_id', $line->id)
                    ->orWhereHas('applicantContract', fn ($relation) => $relation
                        ->withTrashed()
                        ->where('contract_id', $line->contract_id)
                        ->where('service', $line->service));
            })
            ->exists();
        if ($alreadyOpen) {
            return null;
        }

        // Each service's delivery work lands on its own board (see
        // ServiceBoard) so the team sees scholarship work on the Scholarship
        // board rather than a single shared Contracts board.
        $board = ServiceBoard::firstOrCreateFor(ServiceBoard::titleFor($line->service));

        $label = $line->serviceLabel();

        $task = Task::create([
            'board_id' => $board->id,
            'applicant_id' => $applicant->id,
            'applicant_contract_id' => $line->id,
            'title' => "Deliver {$label} — {$applicant->name}",
            'description' => $this->describe($line),
            'priority' => 'high',
            'due_date' => now()->addDays(7)->toDateString(),
            'order' => (Task::query()->where('board_id', $board->id)->max('order') ?? -1) + 1,
        ]);

        // The counselor owns the relationship and the admission employee
        // handles intake, so both are assigned; the actor is only a fallback
        // when the case has neither specialist.
        $applicant->loadMissing(['responsibleEmployee', 'admissionEmployee']);
        $assignees = array_values(array_unique(array_filter([
            $applicant->responsibleEmployee?->user_id,
            $applicant->admissionEmployee?->user_id,
        ])));
        if ($assignees === []) {
            $assignees = array_values(array_filter([$actor?->id]));
        }
        if ($assignees !== []) {
            $task->users()->sync($assignees);
            $board->users()->syncWithoutDetaching($assignees);
            $this->notifications->taskAssigned($task, $assignees, $actor?->id);
        }

        return $task;
    }

    /** Human-readable context for the person delivering the contract. */
    private function describe(ApplicantContract $line): string
    {
        $currency = $line->currency?->code;
        $contract = $line->contract;

        return implode(' · ', array_filter([
            'Contract '.($contract?->contract_number ?? "#{$line->contract_id}"),
            'Service fee: '.($line->fee ?? '—').($currency ? " {$currency}" : ''),
            'Payment: '.PaymentStatus::label($line->payment_status),
            $contract?->start_date ? 'Starts '.$contract->start_date->toDateString() : null,
        ]));
    }
}
