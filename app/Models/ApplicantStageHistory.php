<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantStageHistory extends Model
{
    protected $fillable = ['applicant_id', 'old_stage_id', 'new_stage_id', 'changed_by', 'changed_at', 'note'];
    protected $casts = ['changed_at' => 'immutable_datetime'];
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function oldStage(): BelongsTo { return $this->belongsTo(ApplicantStage::class, 'old_stage_id'); }
    public function newStage(): BelongsTo { return $this->belongsTo(ApplicantStage::class, 'new_stage_id'); }
    public function changer(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
