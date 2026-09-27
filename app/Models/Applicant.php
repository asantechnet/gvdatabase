<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An applicant is the single customer-facing record: it carries both the
 * visa case (stage, country, university, ...) and the billing/CRM party
 * identity (name, contact, email, address, tax number, marketer).
 *
 * The former `Customer` entity was merged into this model, so every
 * `applicant_id` foreign key in the system points at one of these rows and
 * `Customer` no longer exists.
 */
class Applicant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_applicant_id',
        'name',
        'contact',
        'email',
        'address',
        'tax_number',
        'marketer',
        'marketer_id',
        'date',
        'responsible_employee_id',
        'country_id',
        'university_id',
        'education_level_id',
        'language_id',
        'semester_id',
        'current_stage_id',
        'application_date',
        'status',
        'notes',
        'profile_data',
        'profile_completed_at',
    ];

    protected $casts = [
        'application_date' => 'date:Y-m-d',
        'date' => 'date',
        'profile_data' => 'array',
        'profile_completed_at' => 'datetime',
    ];

    /**
     * The root of a shared billing party. Was `applicants.customer_id` when a
     * customer could own several cases; the earliest case of the group is the
     * root and its siblings point at it.
     */
    public function parentApplicant(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_applicant_id');
    }

    /** Sibling cases that share this record's billing party. */
    public function childApplicants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_applicant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    /** The employee who acquired this record. Absorbed from `customers`. */
    public function marketerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marketer_id');
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
    public function attachments(): HasMany { return $this->hasMany(ApplicantAttachment::class); }

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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Contracted services sold as part of this visa case. */
    public function services(): HasMany
    {
        return $this->hasMany(ContractService::class);
    }

    /** Contracted service lines reached through this applicant's contracts. */
    public function contractServices(): HasManyThrough
    {
        return $this->hasManyThrough(ContractService::class, Contract::class);
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

    /** CRM conversation history. Absorbed from `customers`. */
    public function interactions(): MorphMany
    {
        return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at');
    }
}
