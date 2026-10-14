<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Employee;
use App\Models\University;
use App\Services\ApplicantServiceDetails;
use App\Services\ApplicantTaskService;
use App\Services\CaseWinService;
use App\Services\EducationalWorkflowNotificationService;
use App\Support\AgencyStaffAssignment;
use App\Support\BuiltInService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::with($this->relations())->withCount('attachments');
        if ($request->user()->hasPermission('view_applicant_financials')) {
            $query->with('applicantContracts.currency', 'applicantContracts.contract', 'applicantContracts.currentStage');
        }
        $this->scopeCasesToActor($query, $request);
        $this->applyFilters($query, $request);
        $sort = $request->input('sort', 'created_at');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['created_at', 'id'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'created_at', $dir);

        $page = $query->paginate($request->integer('per_page', 25))
            ->through(fn (Applicant $applicant) => $this->aliasDetails($applicant));

        return response()->json(['success' => true, 'data' => $page->appends($request->query())]);
    }

    public function pipeline(Request $request)
    {
        $stages = $this->stagesForContext($request->integer('country_id') ?: null, $request->integer('university_id') ?: null, $this->requestedService($request));
        $columns = $stages->map(function (ApplicantStage $stage) use ($request) {
            $query = Applicant::query();
            $this->scopeCasesToActor($query, $request);
            $this->applyFilters($query, $request, $excludeStage = true);
            $query->where('current_stage_id', $stage->id);

            return ['id' => $stage->id, 'key' => $stage->key, 'name' => $stage->name, 'sort_order' => $stage->sort_order, 'is_terminal' => (bool) $stage->is_terminal, 'allowed_next_keys' => $stage->allowed_next_keys ?? [], 'count' => $query->count()];
        });
        $totalsQuery = Applicant::query();
        $this->scopeCasesToActor($totalsQuery, $request);
        $this->applyFilters($totalsQuery, $request, $excludeStage = true);

        return response()->json(['success' => true, 'data' => ['stages' => $columns, 'totals' => ['active' => (clone $totalsQuery)->where('status', 'active')->count(), 'closed' => (clone $totalsQuery)->where('status', 'closed')->count(), 'all' => (clone $totalsQuery)->count()]]]);
    }

    public function show(Applicant $applicant)
    {
        $request = request();
        $this->assertCaseAccess($request, $applicant);

        $relations = [...$this->relations(), 'stageHistory.oldStage', 'stageHistory.newStage', 'stageHistory.changer', 'tasks.attachments', 'universities.university'];
        if ($request->user()->hasPermission('view_applicant_contracts')) {
            $relations[] = 'contracts';
        }
        if ($request->user()->hasPermission('view_applicant_financials')) {
            array_push($relations, 'pendingTransactions.currency', 'incomes', 'expenses');
        }
        if ($request->user()->hasPermission('view_applicant_contracts')) {
            array_push($relations, 'services.currency', 'services.soldByEmployee', 'services.bonusSource', 'services.contract', 'services.currentStage');
        }

        $applicant->load($relations);
        if ($request->user()->hasPermission('view_applicant_contracts')) {
            $applicant->setRelation('services', $applicant->services->map(fn ($service) => $this->presentService($service)));
        }
        $this->aliasDetails($applicant);

        return response()->json(['success' => true, 'data' => $applicant]);
    }

    public function store(Request $request, EducationalWorkflowNotificationService $notifications)
    {
        $applicant = $this->save($request, new Applicant);
        $notifications->applicantAssigned($applicant, $request->user()?->id);

        return response()->json(['success' => true, 'data' => $applicant], 201);
    }

    public function update(Request $request, Applicant $applicant, EducationalWorkflowNotificationService $notifications)
    {
        $this->assertCaseManageAccess($request, $applicant);
        $previousEmployeeId = $applicant->responsible_employee_id;
        $applicant = $this->save($request, $applicant, true);
        if ($previousEmployeeId !== $applicant->responsible_employee_id) {
            $notifications->applicantAssigned($applicant, $request->user()?->id);
        }

        return response()->json(['success' => true, 'data' => $applicant]);
    }

    public function destroy(Applicant $applicant)
    {
        $this->assertCaseManageAccess(request(), $applicant);
        $blocking = ['contracts' => $applicant->contracts()->count(), 'services' => $applicant->services()->count(), 'tasks' => $applicant->tasks()->count(), 'pending_transactions' => $applicant->pendingTransactions()->count(), 'official_finance' => $applicant->incomes()->count() + $applicant->expenses()->count()];
        if (array_sum($blocking) > 0) {
            return response()->json(['code' => 'business.applicant_has_activity', 'blocking' => $blocking], 409);
        } $applicant->delete();

        return response()->noContent();
    }

    public function stages(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->stagesForContext($request->integer('country_id') ?: null, $request->integer('university_id') ?: null, $this->requestedService($request))]);
    }

    public function changeStage(Request $request, Applicant $applicant, EducationalWorkflowNotificationService $notifications)
    {
        $this->assertCaseManageAccess($request, $applicant);
        $data = $request->validate(['stage_id' => ['required', 'integer', 'exists:applicant_stages,id'], 'note' => ['nullable', 'string', 'max:5000']]);
        $next = ApplicantStage::findOrFail($data['stage_id']);
        $current = $applicant->currentStage;
        abort_unless($this->stageAppliesTo($next, $applicant), 422, 'business.applicant_stage_context_mismatch');
        if ($current && ! $current->allowsTransitionTo($next)) {
            throw ValidationException::withMessages(['stage_id' => ["Transition from {$current->name} to {$next->name} is not permitted."]]);
        }
        DB::transaction(function () use ($applicant, $current, $next, $data, $request, $notifications) {
            $applicant->update(['current_stage_id' => $next->id, 'status' => $next->is_terminal ? 'closed' : 'active']);
            $history = ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'old_stage_id' => $current?->id, 'new_stage_id' => $next->id, 'changed_by' => $request->user()?->id, 'changed_at' => now(), 'note' => $data['note'] ?? null]);
            $applicant = $applicant->fresh()->load(['user', 'responsibleEmployee.user']);
            $caseWin = app(CaseWinService::class)->record($applicant, $next, $history);
            app(ApplicantTaskService::class)->ensureForStage($applicant, $next, $request->user());
            $notifications->applicantStageChanged($applicant, $next, $history, $request->user()?->id);
            if ($caseWin) {
                $notifications->caseWon($caseWin, $request->user()?->id);
            }
        });

        return $this->show($applicant->fresh());
    }

    private function save(Request $request, Applicant $applicant, bool $updating = false): Applicant
    {
        $presence = $updating ? 'sometimes' : 'required';
        $data = $request->validate([
            'marketer' => ['prohibited'],
            'marketer_id' => ['prohibited'],
            'name' => [$presence, 'nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'backup_phone' => ['nullable', 'string', 'max:50'],
            'father_phone' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'admission_employee_id' => AgencyStaffAssignment::rules('admission'),
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'notes' => ['nullable', 'string', 'max:10000'],
            // The built-in service this applicant belongs to. It decides which
            // detail field set applies. Defaults to scholarship on creation.
            'service' => ['sometimes', Rule::in(BuiltInService::ALL)],
            // Scholarship detail fields.
            'passport_id' => ['nullable', 'string', 'max:64'],
            'grade_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'university_fee' => ['nullable', 'numeric', 'min:0'],
            'favorite_subject_ids' => ['nullable', 'array'],
            'favorite_subject_ids.*' => ['integer', 'exists:subjects,id'],
            // Test offer detail fields.
            'scholarship_id' => ['nullable', 'integer', 'exists:educational_scholarships,id'],
            'class_id' => ['nullable', 'integer', 'exists:academy_classes,id'],
            'test_id' => ['nullable', 'integer', 'exists:tests,id'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            // Academy detail fields.
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'time_id' => ['nullable', 'integer', 'exists:times,id'],
            'duration_id' => ['nullable', 'integer', 'exists:durations,id'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['integer', 'exists:teachers,id'],
        ]);

        return DB::transaction(function () use ($request, $applicant, $updating, $data) {
            // The applicant is the CRM's party record, so it follows the same
            // ownership rules as visitors.
            $service = $data['service'] ?? $applicant->service ?? BuiltInService::SCHOLARSHIP;
            $scholarship = $applicant->exists ? $applicant->detail() : null;
            $effectiveCountry = array_key_exists('country_id', $data) ? $data['country_id'] : $scholarship?->country_id;
            $effectiveUniversity = array_key_exists('university_id', $data) ? $data['university_id'] : $scholarship?->university_id;
            if (! in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true)) {
                $effectiveCountry = null;
                $effectiveUniversity = null;
            }
            if ($effectiveUniversity) {
                $universityCountry = University::query()->whereKey($effectiveUniversity)->value('country_id');
                abort_unless($universityCountry && (int) $universityCountry === (int) $effectiveCountry, 422, 'business.university_country_mismatch');
            }
            if (! $request->user()->hasPermission('assign_applicant_employee')) {
                $ownEmployeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');
                abort_unless($ownEmployeeId, 422, 'business.employee_profile_missing');
                $scope = app(EmployeeScope::class);
                if ($updating) {
                    // A supervisor with the manage scope may move a case to a team
                    // member; everyone else is pinned to their own employee row.
                    if (array_key_exists('responsible_employee_id', $data)
                        && (int) $data['responsible_employee_id'] !== (int) $applicant->responsible_employee_id
                        && ! $scope->canManageOwnedBy($request->user(), $data['responsible_employee_id'])) {
                        abort(403, 'auth.forbidden');
                    }
                } elseif (isset($data['responsible_employee_id']) && (int) $data['responsible_employee_id'] !== (int) $ownEmployeeId) {
                    abort_unless($scope->canManageOwnedBy($request->user(), $data['responsible_employee_id']), 403, 'auth.forbidden');
                } else {
                    $data['responsible_employee_id'] = $ownEmployeeId;
                }
            }
            // Academy enrolments carry no associated employee name: the class and
            // teachers on the enrolment are the academy's own records. Any
            // assignment sent (including the automatic self-assignment above) is
            // cleared so an academy application never shows a staff member.
            if ($service === BuiltInService::ACADEMY) {
                $data['admission_employee_id'] = null;
                $data['responsible_employee_id'] = null;
            }
            if (! $updating) {
                $stage = $this->stagesForContext($effectiveCountry, $effectiveUniversity, $service)->firstWhere('key', 'new')
                    ?? ApplicantStage::query()->where('key', 'new')->where('service', $service)->firstOrFail();
                $data['current_stage_id'] = $stage->id;
            }
            $applicant->fill($data)->save();
            $details = app(ApplicantServiceDetails::class);
            if (! $updating) {
                $details->sync($applicant, $data);
            } elseif (array_intersect(array_keys($data), [
                'service', 'passport_id', 'grade_score', 'contract_type_id', 'university_fee',
                'favorite_subject_ids', 'test_id', 'skill_ids', 'program_id', 'time_id',
                'duration_id', 'teacher_ids', 'scholarship_id', 'class_id',
                'country_id', 'university_id', 'education_level_id', 'semester_id',
            ]) !== []) {
                $details->sync($applicant, $data);
            }
            if (! $updating) {
                ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'new_stage_id' => $applicant->current_stage_id, 'changed_by' => $request->user()?->id, 'changed_at' => now(), 'note' => 'Case created']);
            }

            return $this->aliasDetails($applicant->fresh()->load($this->relations()));
        });
    }

    /**
     * Expose the applicant's per-service detail row under one stable key.
     *
     * The row lives in whichever of the four detail relations matches the
     * applicant's service, so the response aliases it as `details` — the same
     * shape the frontend uses for every service.
     */
    private function aliasDetails(Applicant $applicant): Applicant
    {
        if ($applicant->relationLoaded('applicantContracts')) {
            $applicant->setRelation('services', $applicant->applicantContracts->map(fn ($line) => $this->presentService($line)));
        }
        $applicant->setRelation('details', $applicant->getRelationValue($applicant->detailRelationName()));
        foreach (['favoriteSubjects' => 'favorite_subject_ids', 'skills' => 'skill_ids', 'teachers' => 'teacher_ids'] as $relation => $key) {
            $applicant->setAttribute($key, $applicant->getRelationValue($relation)->pluck('id')->all());
        }

        return $applicant;
    }

    private function relations(): array
    {
        return [
            'responsibleEmployee', 'admissionEmployee', 'currentStage',
            // The per-service half of the record and its multi-valued fields.
            // All four detail relations are loaded; the resource picks the one
            // matching the applicant's service.
            'privateDetail.country', 'privateDetail.university', 'privateDetail.educationLevel', 'privateDetail.semester', 'scholarshipDetail.scholarship', 'academyDetail.academyClass', 'scholarshipDetail.country', 'scholarshipDetail.university', 'scholarshipDetail.educationLevel', 'scholarshipDetail.semester', 'testDetail.test', 'academyDetail.program', 'academyDetail.time', 'academyDetail.duration',
            'favoriteSubjects', 'skills', 'teachers',
        ];
    }

    private function presentService(ApplicantContract $record): array
    {
        return [
            'id' => $record->id, 'service' => $record->serviceKey(), 'type' => $record->serviceLabel(), 'employee_id' => $record->sold_by_employee_id,
            'contract_id' => $record->contract_id,
            'contract_number' => $record->contract?->contract_number,
            ...$record->only(['application_fee', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee', 'currency_rate']),
            'currency_id' => $record->currency_id,
            'date' => $record->contract?->start_date?->toDateString(), 'fee' => $record->fee, 'status' => $record->contract?->status,
            'current_stage_id' => $record->current_stage_id,
            'current_stage' => $record->relationLoaded('currentStage') && $record->currentStage ? [
                'id' => $record->currentStage->id, 'key' => $record->currentStage->key, 'name' => $record->currentStage->name,
                'is_terminal' => (bool) $record->currentStage->is_terminal, 'allowed_next_keys' => $record->currentStage->allowed_next_keys ?? [],
            ] : null,
            'currency' => $record->currency,
            'bonus_source' => $record->relationLoaded('bonusSource') ? $record->bonusSource : null,
        ];
    }

    private function applyFilters($query, Request $request, bool $excludeStage = false): void
    {
        $request->validate(['date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        if (! $excludeStage && $request->filled('stage')) {
            $stage = $request->input('stage');
            $query->whereHas('currentStage', fn ($q) => $q->where('key', $stage));
        }
        if ($request->filled('status') && in_array($request->input('status'), ['active', 'closed'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('service') && BuiltInService::isValid($request->input('service'))) {
            $query->where('service', $request->input('service'));
        }
        if ($request->filled('board')) {
            match ($request->input('board')) {
                'operation' => $query->where('status', 'active'),
                'final' => $query->whereHas('currentStage', fn ($q) => $q->where('key', ApplicantStage::COMPLETED_KEY)),
                'cancelled' => $query->where('status', 'closed')->whereHas('currentStage', fn ($q) => $q->whereIn('key', ['cancelled', 'rejected', 'withdrawn'])),
                default => null,
            };
        }
        if ($request->filled('responsible_employee_id')) {
            $query->where('responsible_employee_id', $request->integer('responsible_employee_id'));
        }
        foreach (['country_id', 'university_id', 'education_level_id', 'semester_id'] as $field) {
            if ($request->filled($field)) {
                $query->whereScholarshipField($field, $request->integer($field));
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%");
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
    }

    private function assertCaseAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }
        abort_unless(app(EmployeeScope::class)->canViewOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }

    /** Change access: own cases, or a subordinate's case with the manage scope. */
    private function assertCaseManageAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }
        abort_unless(app(EmployeeScope::class)->canManageOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }

    private function scopeCasesToActor($query, Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }
        $query->whereIn('responsible_employee_id', app(EmployeeScope::class)->visibleEmployeeIds($user));
    }

    private function stagesForContext(?int $countryId, ?int $universityId, ?string $service = null)
    {
        return ApplicantStage::query()->where('is_active', true)
            ->when($service, fn ($q) => $q->where('service', $service))
            ->where(function ($query) use ($countryId, $universityId) {
            $query->whereNull('country_id')->whereNull('university_id');
            if ($countryId) {
                $query->orWhere(fn ($q) => $q->where('country_id', $countryId)->whereNull('university_id'));
            }
            if ($universityId) {
                $query->orWhere('university_id', $universityId);
            }
        })->orderBy('sort_order')->get()->unique('key')->values();
    }

    /** The service filter for a request, only when it is a real built-in service. */
    private function requestedService(Request $request): ?string
    {
        $service = $request->input('service');

        return BuiltInService::isValid($service) ? $service : null;
    }

    private function stageAppliesTo(ApplicantStage $stage, Applicant $applicant): bool
    {
        // A stage scoped to another service can never apply to this applicant.
        if ($stage->service && $stage->service !== $applicant->service) {
            return false;
        }
        if (! in_array($applicant->service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true)) {
            return ! $stage->country_id && ! $stage->university_id;
        }

        $detail = $applicant->detail();
        $countryId = $detail?->country_id;
        $universityId = $detail?->university_id;

        return (! $stage->country_id || (int) $stage->country_id === (int) $countryId) && (! $stage->university_id || (int) $stage->university_id === (int) $universityId);
    }
}
