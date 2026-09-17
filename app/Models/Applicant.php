<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['customer_id', 'responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id', 'current_stage_id', 'application_date', 'status', 'notes'];
    protected $casts = ['application_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function responsibleEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'responsible_employee_id'); }
    public function country(): BelongsTo { return $this->belongsTo(Country::class); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function educationLevel(): BelongsTo { return $this->belongsTo(EducationLevel::class); }
    public function language(): BelongsTo { return $this->belongsTo(Language::class); }
    public function currentStage(): BelongsTo { return $this->belongsTo(ApplicantStage::class, 'current_stage_id'); }
    public function stageHistory(): HasMany { return $this->hasMany(ApplicantStageHistory::class)->latest('changed_at'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
}
