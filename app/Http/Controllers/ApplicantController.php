<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Services\ApplicantTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::with($this->relations());
        $this->applyFilters($query, $request);
        $sort = $request->input('sort', 'created_at');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['application_date', 'created_at', 'id'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'created_at', $dir);

        return response()->json(['success' => true, 'data' => $query->paginate($request->integer('per_page', 25))->appends($request->query())]);
    }

    public function pipeline(Request $request)
    {
        $stages = ApplicantStage::query()->where('is_active', true)->orderBy('sort_order')->get();
        $columns = $stages->map(function (ApplicantStage $stage) use ($request) {
            $query = Applicant::query();
            $this->applyFilters($query, $request, $excludeStage = true);
            $query->where('current_stage_id', $stage->id);

            return ['id' => $stage->id, 'key' => $stage->key, 'name' => $stage->name, 'sort_order' => $stage->sort_order, 'is_terminal' => (bool) $stage->is_terminal, 'allowed_next_keys' => $stage->allowed_next_keys ?? [], 'count' => $query->count()];
        });
        $totalsQuery = Applicant::query();
        $this->applyFilters($totalsQuery, $request, $excludeStage = true);

        return response()->json(['success' => true, 'data' => ['stages' => $columns, 'totals' => ['active' => (clone $totalsQuery)->where('status', 'active')->count(), 'closed' => (clone $totalsQuery)->where('status', 'closed')->count(), 'all' => (clone $totalsQuery)->count()]]]);
    }
    public function show(Applicant $applicant) { return response()->json(['success' => true, 'data' => $applicant->load([...$this->relations(), 'stageHistory.oldStage', 'stageHistory.newStage', 'stageHistory.changer', 'tasks.attachments', 'contracts', 'pendingTransactions.currency', 'incomes', 'expenses', 'scholarships.currency', 'testOffers.currency', 'universities.university'])]); }
    public function store(Request $request) { return response()->json(['success' => true, 'data' => $this->save($request, new Applicant)], 201); }
    public function update(Request $request, Applicant $applicant) { return response()->json(['success' => true, 'data' => $this->save($request, $applicant, true)]); }
    public function destroy(Applicant $applicant) { if ($applicant->contracts()->exists()) return response()->json(['code' => 'business.applicant_has_contracts'], 409); $applicant->delete(); return response()->noContent(); }

    public function stages() { return response()->json(['success' => true, 'data' => ApplicantStage::query()->where('is_active', true)->orderBy('sort_order')->get()]); }
    public function changeStage(Request $request, Applicant $applicant)
    {
        $data = $request->validate(['stage_id' => ['required', 'integer', 'exists:applicant_stages,id'], 'note' => ['nullable', 'string', 'max:5000']]);
        $next = ApplicantStage::findOrFail($data['stage_id']); $current = $applicant->currentStage;
        if ($current && ! $current->allowsTransitionTo($next)) throw ValidationException::withMessages(['stage_id' => ["Transition from {$current->name} to {$next->name} is not permitted."]]);
        DB::transaction(function () use ($applicant, $current, $next, $data, $request) { $applicant->update(['current_stage_id' => $next->id, 'status' => $next->is_terminal ? 'closed' : 'active']); ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'old_stage_id' => $current?->id, 'new_stage_id' => $next->id, 'changed_by' => $request->user()?->id, 'changed_at' => now(), 'note' => $data['note'] ?? null]); app(ApplicantTaskService::class)->ensureForStage($applicant->fresh()->load(['customer', 'responsibleEmployee']), $next, $request->user()); });
        return $this->show($applicant->fresh());
    }

    private function save(Request $request, Applicant $applicant, bool $updating = false): Applicant
    {
        $presence = $updating ? 'sometimes' : 'required';
        $data = $request->validate(['customer_id' => [$presence, 'integer', 'exists:customers,id'], 'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'country_id' => ['nullable', 'integer', 'exists:countries,id'], 'university_id' => ['nullable', 'integer', 'exists:universities,id'], 'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'], 'language_id' => ['nullable', 'integer', 'exists:languages,id'], 'application_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:10000']]);
        if (! $updating) { $stage = ApplicantStage::query()->where('key', 'new')->firstOrFail(); $data['current_stage_id'] = $stage->id; }
        $applicant->fill($data)->save();
        if (! $updating) ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'new_stage_id' => $applicant->current_stage_id, 'changed_by' => $request->user()?->id, 'changed_at' => now(), 'note' => 'Case created']);
        return $applicant->fresh()->load($this->relations());
    }
    private function relations(): array { return ['customer', 'responsibleEmployee', 'country', 'university.country', 'educationLevel', 'language', 'currentStage']; }

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
        foreach (['customer_id', 'responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id'] as $field) {
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
                    $q->orWhere('id', (int) $search)->orWhere('customer_id', (int) $search);
                }
                $q->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('contact', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
    }
}
