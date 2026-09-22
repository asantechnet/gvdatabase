<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseWin extends Model
{
    use Auditable;

    protected $fillable = ['applicant_id', 'employee_id', 'applicant_stage_history_id', 'won_at', 'stage_key'];

    protected $casts = ['won_at' => 'immutable_datetime'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function stageHistory(): BelongsTo
    {
        return $this->belongsTo(ApplicantStageHistory::class, 'applicant_stage_history_id');
    }
}
