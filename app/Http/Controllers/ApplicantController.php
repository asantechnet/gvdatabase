<?php

namespace App\Http\Controllers;

use App\Support\CrmMarketerAttribution;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\ContractService;
use App\Models\Employee;
use App\Models\University;
use App\Services\ApplicantTaskService;
use App\Services\CaseWinService;
use App\Services\EducationalWorkflowNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::with($this->relations());
        $this->scopeCasesToActor($query, $request);
        $this->applyFilters($query, $request);
        $sort = $request->input('sort', 'created_at');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['application_date', 'created_at', 'id'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'created_at', $dir);

        return response()->json(['success' => true, 'data' => $query->paginate($request->integer('per_page', 25))->appends($request->query())]);
    }

    public function pipeline(Request $request)
    {
        $stages = $this->stagesForContext($request->integer('country_id') ?: null, $request->integer('university_id') ?: null);
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
        if ($request->user()->hasPermission('view_applicant_services')) {
            array_push($relations, 'services.service.currency', 'services.currency', 'services.soldByEmployee', 'services.customerPackage.package', 'services.bonusSource', 'services.contract');
        }

        $applicant->load($relations);
        if ($request->user()->hasPermission('view_applicant_services')) {
            $applicant->setRelation('services', $applicant->services->map(fn ($service) => $this->presentService($service)));
        }

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
        $this->assertCaseAccess($request, $applicant);
        $previousEmployeeId = $applicant->responsible_employee_id;
        $applicant = $this->save($request, $applicant, true);
        if ($previousEmployeeId !== $applicant->responsible_employee_id) {
            $notifications->applicantAssigned($applicant, $request->user()?->id);
        }

        return response()->json(['success' => true, 'data' => $applicant]);
    }

    public function destroy(Applicant $applicant)
    {
        $this->assertCaseAccess(request(), $applicant);
        $blocking = ['contracts' => $applicant->contracts()->count(), 'services' => $applicant->services()->count(), 'tasks' => $applicant->tasks()->count(), 'pending_transactions' => $applicant->pendingTransactions()->count(), 'official_finance' => $applicant->incomes()->count() + $applicant->expenses()->count()];
        if (array_sum($blocking) > 0) {
            return response()->json(['code' => 'business.applicant_has_activity', 'blocking' => $blocking], 409);
        } $applicant->delete();

        return response()->noContent();
    }

    public function stages(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->stagesForContext($request->integer('country_id') ?: null, $request->integer('university_id') ?: null)]);
    }

    public function changeStage(Request $request, Applicant $applicant, EducationalWorkflowNotificationService $notifications)
    {
        $this->assertCaseAccess($request, $applicant);
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
            'name' => [$presence, 'nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'marketer' => ['nullable', 'string', 'max:255'],
            'marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'date' => ['nullable', 'date'],
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'application_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        // The applicant is the CRM's party record, so it follows the same
        // ownership rules as leads and visitors.
        $data = CrmMarketerAttribution::apply($request, $data, creating: ! $updating);
        $effectiveCountry = $data['country_id'] ?? $applicant->country_id;
        $effectiveUniversity = $data['university_id'] ?? $applicant->university_id;
        if ($effectiveUniversity) {
            $universityCountry = University::query()->whereKey($effectiveUniversity)->value('country_id');
            abort_unless($universityCountry && (int) $universityCountry === (int) $effectiveCountry, 422, 'business.university_country_mismatch');
        }
        if (! $request->user()->hasPermission('assign_applicant_employee')) {
            $ownEmployeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');
            abort_unless($ownEmployeeId, 422, 'business.employee_profile_missing');
            if ($updating) {
                abort_if(array_key_exists('responsible_employee_id', $data) && (int) $data['responsible_employee_id'] !== (int) $applicant->responsible_employee_id, 403, 'auth.forbidden');
                unset($data['responsible_employee_id']);
            } else {
                abort_if(isset($data['responsible_employee_id']) && (int) $data['responsible_employee_id'] !== (int) $ownEmployeeId, 403, 'auth.forbidden');
                $data['responsible_employee_id'] = $ownEmployeeId;
            }
        }
        if (! $updating) {
            $stage = $this->stagesForContext($effectiveCountry, $effectiveUniversity)->firstWhere('key', 'new') ?? ApplicantStage::query()->where('key', 'new')->firstOrFail();
            $data['current_stage_id'] = $stage->id;
        }
        $applicant->fill($data)->save();
        if (! $updating) {
            ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'new_stage_id' => $applicant->current_stage_id, 'changed_by' => $request->user()?->id, 'changed_at' => now(), 'note' => 'Case created']);
        }

        return $applicant->fresh()->load($this->relations());
    }

    private function relations(): array
    {
        return ['responsibleEmployee', 'country', 'university.country', 'educationLevel', 'language', 'semester', 'currentStage'];
    }

    private function presentService(ContractService $record): array
    {
        return [
            'id' => $record->id, 'service_id' => $record->service_id, 'type' => $record->service->type, 'category' => $record->service->category, 'employee_id' => $record->sold_by_employee_id,
            'currency_id' => $record->currency_id ?? $record->service->currency_id, 'package_type_id' => $record->customerPackage?->package_id,
            'country_id' => $record->country_id, 'education_level_id' => $record->education_level_id, 'language_id' => $record->language_id,
            'date' => $record->contract?->start_date?->toDateString(), 'fee' => $record->fee ?? $record->service->fee, 'status' => $record->contract?->status,
            'currency' => $record->currency ?? $record->service->currency, 'package_type' => $record->customerPackage?->package,
            'bonus_source' => $record->bonusSource,
        ];
    }

    private function applyFilters($query, Request $request, bool $excludeStage = false): void
    {
        if (! $excludeStage && $request->filled('stage')) {
            $stage = $request->input('stage');
            $query->whereHas('currentStage', fn ($q) => $q->where('key', $stage));
        }
        if ($request->filled('status') && in_array($request->input('status'), ['active', 'closed'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('board')) {
            match ($request->input('board')) {
                'operation' => $query->where('status', 'active'),
                'final' => $query->whereHas('currentStage', fn ($q) => $q->where('key', 'completed')),
                'cancelled' => $query->where('status', 'closed')->whereHas('currentStage', fn ($q) => $q->whereIn('key', ['cancelled', 'rejected', 'withdrawn'])),
                default => null,
            };
        }
        foreach (['responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id', 'semester_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->integer($field));
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('application_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('application_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%");
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
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
        abort_unless($applicant->responsibleEmployee?->user_id === $user->id, 403, 'auth.forbidden');
    }

    private function scopeCasesToActor($query, Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }
        $query->whereHas('responsibleEmployee', fn ($employee) => $employee->where('user_id', $user->id));
    }

    private function stagesForContext(?int $countryId, ?int $universityId)
    {
        return ApplicantStage::query()->where('is_active', true)->where(function ($query) use ($countryId, $universityId) {
            $query->whereNull('country_id')->whereNull('university_id');
            if ($countryId) {
                $query->orWhere(fn ($q) => $q->where('country_id', $countryId)->whereNull('university_id'));
            }
            if ($universityId) {
                $query->orWhere('university_id', $universityId);
            }
        })->orderBy('sort_order')->get()->unique('key')->values();
    }

    private function stageAppliesTo(ApplicantStage $stage, Applicant $applicant): bool
    {
        return (! $stage->country_id || (int) $stage->country_id === (int) $applicant->country_id) && (! $stage->university_id || (int) $stage->university_id === (int) $applicant->university_id);
    }
}
