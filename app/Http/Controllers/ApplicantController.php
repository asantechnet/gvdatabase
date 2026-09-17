<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['success' => true, 'data' => Applicant::with($this->relations())->when($request->input('stage'), fn ($q, $stage) => $q->whereHas('currentStage', fn ($q) => $q->where('key', $stage)))->latest()->paginate($request->integer('per_page', 25))]);
    }
    public function show(Applicant $applicant) { return response()->json(['success' => true, 'data' => $applicant->load([...$this->relations(), 'stageHistory.oldStage', 'stageHistory.newStage', 'stageHistory.changer', 'tasks', 'contracts'])]); }
    public function store(Request $request) { return response()->json(['success' => true, 'data' => $this->save($request, new Applicant)], 201); }
    public function update(Request $request, Applicant $applicant) { return response()->json(['success' => true, 'data' => $this->save($request, $applicant, true)]); }
    public function destroy(Applicant $applicant) { if ($applicant->contracts()->exists()) return response()->json(['code' => 'business.applicant_has_contracts'], 409); $applicant->delete(); return response()->noContent(); }

    public function stages() { return response()->json(['success' => true, 'data' => ApplicantStage::query()->where('is_active', true)->orderBy('sort_order')->get()]); }
    public function changeStage(Request $request, Applicant $applicant)
    {
        $data = $request->validate(['stage_id' => ['required', 'integer', 'exists:applicant_stages,id'], 'note' => ['nullable', 'string', 'max:5000']]);
        $next = ApplicantStage::findOrFail($data['stage_id']); $current = $applicant->currentStage;
        if ($current && ! $current->allowsTransitionTo($next)) throw ValidationException::withMessages(['stage_id' => ["Transition from {$current->name} to {$next->name} is not permitted."]]);
        DB::transaction(function () use ($applicant, $current, $next, $data, $request) { $applicant->update(['current_stage_id' => $next->id, 'status' => $next->is_terminal ? 'closed' : 'active']); ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'old_stage_id' => $current?->id, 'new_stage_id' => $next->id, 'changed_by' => $request->user()?->id, 'changed_at' => now(), 'note' => $data['note'] ?? null]); });
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
}
