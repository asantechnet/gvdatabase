<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PendingTransaction extends Model
{
    use Auditable, SoftDeletes;

    public const DRAFT = 'draft'; public const SUBMITTED = 'submitted'; public const UNDER_REVIEW = 'under_review'; public const APPROVED = 'approved'; public const REJECTED = 'rejected'; public const POSTED = 'posted';
    protected $fillable = ['transaction_type','reason','amount','currency_id','applicant_id','employee_id','requested_by','approver_id','approval_status','rejection_reason','requested_at','approved_at','rejected_at','posted_at','posted_record_type','posted_record_id'];
    protected $casts = ['amount' => 'decimal:6', 'requested_at'=>'datetime','approved_at'=>'datetime','rejected_at'=>'datetime','posted_at'=>'datetime'];
    public function postedRecord(): MorphTo { return $this->morphTo(); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
