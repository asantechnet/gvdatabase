<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['visitor_id', 'customer_id', 'applicant_id', 'name', 'company', 'contact', 'email', 'service_interest', 'marketer', 'marketer_id', 'stage', 'probability', 'probability_is_manual', 'estimated_value', 'next_follow_up_at', 'notes', 'converted_at', 'owner_id'];

    protected function casts(): array
    {
        return ['probability' => 'integer', 'probability_is_manual' => 'boolean', 'estimated_value' => 'decimal:4', 'next_follow_up_at' => 'date:Y-m-d', 'converted_at' => 'immutable_datetime'];
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function marketerEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'marketer_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function visitor(): BelongsTo { return $this->belongsTo(Visitor::class); }
    public function interactions(): MorphMany { return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at'); }
}
