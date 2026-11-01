<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use Illuminate\Validation\ValidationException;

/**
 * The single guard for every applicant and service-line stage transition.
 *
 * Two rules live here so the UI copy and the server response cannot drift:
 * - a forward, non-terminal move that leaves a case's initial stage needs at
 *   least one labeled document on the case;
 * - every terminal move needs a written reason for the permanent history row.
 */
class ApplicantDocumentReadiness
{
    /** Whether the case has at least one labeled document. */
    public function ready(Applicant $applicant): bool
    {
        return $applicant->attachments()->exists();
    }

    /** Reject the request when the case has no labeled document yet. */
    public function require(Applicant $applicant, string $field = 'applicant_id'): void
    {
        if (! $this->ready($applicant)) {
            throw ValidationException::withMessages([$field => ['business.applicant_document_required']]);
        }
    }

    /**
     * Validate one transition before anything is written.
     *
     * Both stage controllers call this, so a case and its service line can
     * never disagree about when a document or a closing reason is required.
     */
    public function assertTransition(
        Applicant $applicant,
        ?ApplicantStage $from,
        ApplicantStage $next,
        ?string $note,
        string $stageField = 'stage_id',
        string $noteField = 'note',
    ): void {
        if ($next->is_terminal && trim((string) $note) === '') {
            throw ValidationException::withMessages([$noteField => ['business.terminal_stage_note_required']]);
        }

        if ($this->requiresDocument($applicant, $from, $next)) {
            $this->require($applicant, $stageField);
        }
    }

    /**
     * A forward, non-terminal move out of the initial stage needs a document.
     *
     * Backward moves (an appeal back into the pipeline) and terminal moves are
     * not gated here; the latter are covered by the reason rule.
     */
    public function requiresDocument(Applicant $applicant, ?ApplicantStage $from, ApplicantStage $next): bool
    {
        if (! $from || $next->is_terminal) {
            return false;
        }

        if ((int) $next->sort_order <= (int) $from->sort_order) {
            return false;
        }

        $initial = $this->initialStage($applicant, $from);

        // An undeterminable catalogue must never freeze existing cases.
        return $initial !== null && (int) $initial->id === (int) $from->id;
    }

    /**
     * The initial stage of the case's workflow: its lowest-ordered active,
     * non-terminal stage for the same service.
     */
    private function initialStage(Applicant $applicant, ApplicantStage $from): ?ApplicantStage
    {
        $service = $from->service ?? $applicant->service;

        return ApplicantStage::query()
            ->where('is_active', true)
            ->where('is_terminal', false)
            ->when($service, fn ($query) => $query->where('service', $service))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
