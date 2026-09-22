<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\CaseWin;
use Illuminate\Validation\ValidationException;

class CaseWinService
{
    public function record(Applicant $applicant, ApplicantStage $stage, ApplicantStageHistory $history): ?CaseWin
    {
        if ($stage->key !== 'visa_approved') {
            return null;
        }
        if (! $applicant->responsible_employee_id) {
            throw ValidationException::withMessages([
                'stage_id' => ['Assign a responsible employee before marking a case as won.'],
            ]);
        }

        return CaseWin::query()->firstOrCreate(
            ['applicant_id' => $applicant->id],
            [
                'employee_id' => $applicant->responsible_employee_id,
                'applicant_stage_history_id' => $history->id,
                'won_at' => $history->changed_at,
                'stage_key' => $stage->key,
            ],
        );
    }
}
