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

    protected $fillable = ['user_id', 'customer_id', 'responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id', 'semester_id', 'current_stage_id', 'application_date', 'status', 'notes', 'profile_data', 'profile_completed_at'];

    protected $casts = ['application_date' => 'date:Y-m-d', 'profile_data' => 'array', 'profile_completed_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(ApplicantStage::class, 'current_stage_id');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(ApplicantStageHistory::class)->latest('changed_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function pendingTransactions(): HasMany
    {
        return $this->hasMany(PendingTransaction::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(CustomerService::class)->whereHas('service', fn ($query) => $query->where('category', 'scholarship'));
    }

    public function testOffers(): HasMany
    {
        return $this->hasMany(CustomerService::class)->whereHas('service', fn ($query) => $query->where('category', 'test_offer'));
    }

    public function customerServices(): HasMany
    {
        return $this->hasMany(CustomerService::class);
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function caseWin()
    {
        return $this->hasOne(CaseWin::class);
    }

    public function universities(): HasMany
    {
        return $this->hasMany(ApplicantUniversity::class);
    }
}
