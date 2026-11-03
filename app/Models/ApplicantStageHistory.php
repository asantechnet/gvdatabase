<?php

namespace App\Models;

use App\Services\CommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantStageHistory extends Model
{
    protected $fillable = ['applicant_id', 'applicant_contract_id', 'old_stage_id', 'new_stage_id', 'changed_by', 'changed_at', 'note'];

    protected $casts = ['changed_at' => 'immutable_datetime'];

    /**
     * The timeline must say which workflow a row belongs to. A case-level move
     * has no line; a service move names its contract so two lines on one case
     * cannot be read as one sequence.
     */
    protected $appends = ['contract_number', 'contract_role', 'parent_contract_number', 'is_case_level'];

    protected static function booted(): void
    {
        // A stage transition is the event that decides commission: a case that
        // reaches the terminal `completed` stage earns for its contracted
        // service lines, and one that leaves it — or closes at an exit stage —
        // stops earning. Hooking the history row rather than the applicant
        // update means the accrual is dated by the transition itself, and every
        // path that moves a case (controller, portal, seeder) is covered.
        static::created(function (ApplicantStageHistory $history): void {
            $applicant = $history->applicant;
            if (! $applicant) {
                return;
            }

            app(CommissionService::class)->syncForApplicant($applicant, $history->changed_at);
        });
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function getContractNumberAttribute(): ?string
    {
        return $this->applicantContract?->contract?->contract_number;
    }

    public function getContractRoleAttribute(): ?string
    {
        return $this->applicantContract?->contract?->contract_role;
    }

    public function getParentContractNumberAttribute(): ?string
    {
        return $this->applicantContract?->contract?->parentContract?->contract_number;
    }

    public function getIsCaseLevelAttribute(): bool
    {
        return $this->applicant_contract_id === null;
    }

    /** The service line this transition belongs to, when it was a line move. */
    public function applicantContract(): BelongsTo
    {
        return $this->belongsTo(ApplicantContract::class, 'applicant_contract_id');
    }

    public function oldStage(): BelongsTo
    {
        return $this->belongsTo(ApplicantStage::class, 'old_stage_id');
    }

    public function newStage(): BelongsTo
    {
        return $this->belongsTo(ApplicantStage::class, 'new_stage_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
