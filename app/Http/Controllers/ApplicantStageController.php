<?php

namespace App\Http\Controllers;

use App\Models\ApplicantStage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicantStageController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['country_id' => ['nullable', 'exists:countries,id'], 'university_id' => ['nullable', 'exists:universities,id'], 'active' => ['nullable', 'boolean'], 'all' => ['nullable', 'boolean']]);
        $query = ApplicantStage::query()->when(array_key_exists('active', $data), fn ($q) => $q->where('is_active', $data['active']));
        if (! ($data['all'] ?? false)) {
            $countryId = $data['country_id'] ?? null;
            $universityId = $data['university_id'] ?? null;
            $query->where(function ($q) use ($countryId, $universityId) {
                $q->whereNull('country_id')->whereNull('university_id');
                if ($countryId) $q->orWhere(fn ($stage) => $stage->where('country_id', $countryId)->whereNull('university_id'));
                if ($universityId) $q->orWhere('university_id', $universityId);
            });
        }
        return response()->json(['success' => true, 'data' => $query->orderBy('sort_order')->get()]);
    }
    public function store(Request $request) { return response()->json(['success' => true, 'data' => ApplicantStage::create($this->validated($request))], 201); }
    public function update(Request $request, ApplicantStage $applicantStage) { $applicantStage->update($this->validated($request, true)); return response()->json(['success' => true, 'data' => $applicantStage->fresh()]); }
    public function destroy(ApplicantStage $applicantStage) { abort_if($applicantStage->id && \App\Models\Applicant::where('current_stage_id', $applicantStage->id)->exists(), 409, 'business.applicant_stage_in_use'); $applicantStage->delete(); return response()->noContent(); }
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate(['key' => [$required, 'string', 'max:100', 'alpha_dash', Rule::unique('applicant_stages', 'key')->ignore($request->route('applicantStage'))], 'name' => [$required, 'string', 'max:255'], 'country_id' => ['nullable', 'exists:countries,id'], 'university_id' => ['nullable', 'exists:universities,id'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean'], 'is_terminal' => ['sometimes', 'boolean'], 'allowed_next_keys' => ['nullable', 'array'], 'allowed_next_keys.*' => ['string', 'max:100']]);
    }
}
