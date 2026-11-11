<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\ApplicantStageContext;
use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An applicant is the single customer-facing record: it carries the shared
 * billing/CRM party identity while service-specific visa data lives in one of
 * the per-service detail tables.
 *
 * The former `Customer` entity was merged into this model, so every
 * `applicant_id` foreign key in the system points at one of these rows and
 * `Customer` no longer exists.
 *
 * Guardian contact (`father_name`, `father_phone`) belongs to the shared
 * identity. Academic fields such as destination, grade and qualification are
 * stored with the service that asks for them.
 */
class Applicant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $attributes = ['service' => BuiltInService::SCHOLARSHIP];

    protected $fillable = [
        'service',
        'user_id',
        'parent_applicant_id',
        'name',
        'admission_employee_id',
        'father_name',
        'father_phone',
        'backup_phone',
        'contact',
        'email',
        'address',
        'responsible_employee_id',
        'current_stage_id',
        'status',
        'notes',
        'consultant_review',
        'profile_data',
        'profile_completed_at',
    ];

    protected $casts = [
        'service' => 'string',
        'profile_data' => 'array',
        'profile_completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The applicant's stage is a mirror of its service lines once they
        // exist, and the stage of an unlined case is its workflow position.
        // Either way a move may only land on a stage that applies to this
        // applicant's service, test and destination. Initial creation is not
        // blocked: the service detail row may be written after this row.
        static::updating(function (self $applicant): void {
            if (! $applicant->isDirty('current_stage_id') || $applicant->current_stage_id === null) {
                return;
            }

            $stage = ApplicantStage::query()->find($applicant->current_stage_id);
            if (! $stage) {
                return;
            }

            $service = $applicant->service ?? BuiltInService::SCHOLARSHIP;
            if (! app(ApplicantStageContext::class)->appliesTo($applicant, $stage, $service)) {
                throw ValidationException::withMessages(['stage_id' => ['business.applicant_stage_context_mismatch']]);
            }
        });
    }

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
    public function admissionEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'admission_employee_id');
    }

    /** Every responsible employee on the case. The singular column is legacy. */
    public function responsibleEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'applicant_responsible_employees')
            ->withTimestamps();
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

    public function attachments(): HasMany
    {
        return $this->hasMany(ApplicantAttachment::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Contracted services sold as part of this visa case. */
    public function services(): HasMany
    {
        return $this->hasMany(ApplicantContract::class);
    }

    /** Contracted service lines reached through this applicant's contracts. */
    /**
     * True when the case sits in the terminal stage that completes it.
     *
     * This is the state that earns commission for the case's contracted
     * service lines, and it is the only state that does: a case anywhere else
     * (including the other terminal stages) does not pay out.
     */
    public function isCaseCompleted(): bool
    {
        return $this->currentStage?->isCompletedStage() === true;
    }

    /**
     * Overall payment state across every contracted service line.
     *
     * Null when the applicant has no lines or the lines were not loaded, so the
     * applications list can show a single Paid / Partially Paid / Unpaid chip.
     */
    public function overallPaymentStatus(): ?string
    {
        if (! $this->relationLoaded('applicantContracts') || $this->applicantContracts->isEmpty()) {
            return null;
        }

        $payable = (float) $this->applicantContracts->sum(fn (ApplicantContract $line) => $line->payableTotal());
        $paid = (float) $this->applicantContracts->sum(fn (ApplicantContract $line) => (float) $line->amount_paid);

        return PaymentStatus::derive($paid, $payable);
    }

    public function applicantContracts(): HasManyThrough
    {
        return $this->hasManyThrough(ApplicantContract::class, Contract::class);
    }

    public function caseWin()
    {
        return $this->hasOne(CaseWin::class);
    }

    public function universities(): HasMany
    {
        return $this->hasMany(ApplicantUniversity::class);
    }

    /**
     * The relation name that holds this applicant's per-service detail row.
     *
     * Kept as a name rather than a single `details()` relation on purpose:
     * eager loading resolves a relation on a fresh, empty model instance, where
     * `service` is not available yet, so an attribute-driven relation would
     * always fall back to one branch and silently load the wrong table.
     */
    public function detailRelationName(): string
    {
        return match ($this->service) {
            BuiltInService::TEST_OFFER => 'testDetail',
            BuiltInService::ACADEMY => 'academyDetail',
            BuiltInService::PRIVATE => 'privateDetail',
            default => 'scholarshipDetail',
        };
    }

    /** The detail row for this applicant's service (loads it if needed). */
    public function detail(): ?Model
    {
        return $this->getRelationValue($this->detailRelationName());
    }

    public function scholarshipDetail(): HasOne
    {
        return $this->hasOne(ScholarshipApplicantDetail::class);
    }

    public function scopeWhereScholarshipField(Builder $query, string $field, mixed $value): void
    {
        $query->where(fn ($admissions) => $admissions
            ->where(fn ($scholarship) => $scholarship->where('applicants.service', BuiltInService::SCHOLARSHIP)->whereHas('scholarshipDetail', fn ($detail) => $detail->where($field, $value)))
            ->orWhere(fn ($private) => $private->where('applicants.service', BuiltInService::PRIVATE)->whereHas('privateDetail', fn ($detail) => $detail->where($field, $value))));
    }

    /** Include both admissions services in country and visa outcome aggregates. */
    public function scopeJoinAdmissionDetails(Builder $query): void
    {
        $details = DB::table('scholarship_applicant_details')
            ->select('applicant_id', 'country_id')->selectRaw("'scholarship' as service")
            ->whereNull('deleted_at')
            ->unionAll(DB::table('private_applicant_details')
                ->select('applicant_id', 'country_id')->selectRaw("'private' as service")
                ->whereNull('deleted_at'));

        $query->joinSub($details, 'detail', fn ($join) => $join
            ->on('detail.applicant_id', '=', 'applicants.id')
            ->on('detail.service', '=', 'applicants.service'));
    }

    public function privateDetail(): HasOne
    {
        return $this->hasOne(PrivateApplicantDetail::class);
    }

    public function testDetail(): HasOne
    {
        return $this->hasOne(TestApplicantDetail::class);
    }

    public function academyDetail(): HasOne
    {
        return $this->hasOne(AcademyApplicantDetail::class);
    }

    /** A scholarship applicant's favourite subjects. */
    public function favoriteSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'scholarship_applicant_subject')
            ->withTimestamps();
    }

    /** A test-offer applicant's target skills. */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'test_applicant_skill')
            ->withTimestamps();
    }

    /** An academy applicant's assigned teachers (employee records). */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'academy_applicant_teacher', 'applicant_id', 'teacher_id')
            ->withTimestamps();
    }

    /** @return list<string> */
    public static function serviceValues(): array
    {
        return BuiltInService::ALL;
    }

    /** CRM conversation history. Absorbed from `customers`. */
    public function interactions(): MorphMany
    {
        return $this->morphMany(CrmInteraction::class, 'contactable')->latest('contacted_at');
    }
}
