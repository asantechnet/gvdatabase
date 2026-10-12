<?php

namespace App\Models;

use App\Services\CommissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantStageHistory extends Model
{
    protected $fillable = ['applicant_id', 'old_stage_id', 'new_stage_id', 'changed_by', 'changed_at', 'note'];

    protected $casts = ['changed_at' => 'immutable_datetime'];

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
