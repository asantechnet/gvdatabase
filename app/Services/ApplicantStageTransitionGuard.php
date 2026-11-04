<?php

namespace App\Services;

use App\Models\ApplicantStage;
use Illuminate\Validation\ValidationException;

/**
 * The single rule for every stage transition.
 *
 * A terminal move closes a case permanently, so it needs a written reason for
 * the history row. A document is never a stage prerequisite: profile documents
 * are managed independently of the workflow.
 */
class ApplicantStageTransitionGuard
{
    public function assertTransition(ApplicantStage $next, ?string $note, string $noteField = 'note'): void
    {
        if ($next->is_terminal && trim((string) $note) === '') {
            throw ValidationException::withMessages([$noteField => ['business.terminal_stage_note_required']]);
        }
    }
}
