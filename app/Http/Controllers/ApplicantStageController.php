<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Support\BuiltInService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicantStageController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'service' => ['nullable', Rule::in(BuiltInService::ALL)],
            'country_id' => ['nullable', 'exists:countries,id'],
            'university_id' => ['nullable', 'exists:universities,id'],
            'active' => ['nullable', 'boolean'],
            'all' => ['nullable', 'boolean'],
        ]);
        $service = $data['service'] ?? null;
        $query = ApplicantStage::query()
            // A service-scoped request returns that service's list. Without a
            // service (the cross-service list filter) every service's stages are
            // returned once per key, preserving the legacy single-pipeline shape.
            ->when($service, fn ($q) => $q->where('service', $service));
        // Stage pickers only offer live stages; `all=1` is reserved for the
        // stage editor, which must also show inactive rows.
        if (array_key_exists('active', $data)) {
            $query->where('is_active', (bool) $data['active']);
        } elseif (! ($data['all'] ?? false)) {
            $query->where('is_active', true);
        }
        if (! ($data['all'] ?? false)) {
            $countryId = $data['country_id'] ?? null;
            $universityId = $data['university_id'] ?? null;
            $query->where(function ($q) use ($countryId, $universityId) {
                $q->whereNull('country_id')->whereNull('university_id');
                if ($countryId) $q->orWhere(fn ($stage) => $stage->where('country_id', $countryId)->whereNull('university_id'));
                if ($universityId) $q->orWhere('university_id', $universityId);
            });
        }
        $stages = $query->orderBy('sort_order')->get();
        if (! $service) {
            $stages = $stages->unique('key')->values();
        }

        return response()->json(['success' => true, 'data' => $stages]);
    }
    public function store(Request $request) { return response()->json(['success' => true, 'data' => ApplicantStage::create($this->validated($request))], 201); }
    public function update(Request $request, ApplicantStage $applicantStage) { $applicantStage->update($this->validated($request, true)); return response()->json(['success' => true, 'data' => $applicantStage->fresh()]); }
    public function destroy(ApplicantStage $applicantStage)
    {
        abort_if(
            $applicantStage->id && (
                Applicant::where('current_stage_id', $applicantStage->id)->exists()
                || ApplicantContract::where('current_stage_id', $applicantStage->id)->exists()
            ),
            409,
            'business.applicant_stage_in_use'
        );
        $applicantStage->delete();
        return response()->noContent();
    }
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $service = $request->input('service', $request->route('applicantStage')?->service ?? BuiltInService::SCHOLARSHIP);
        $data = $request->validate([
            // A key is unique inside its service. The database keeps the same
            // composite unique index, so a soft-deleted stage still reserves
            // its key and allowed-next references never point at a reused key.
            'key' => [$required, 'string', 'max:100', 'alpha_dash', Rule::unique('applicant_stages', 'key')
                ->where('service', $service)
                ->ignore($request->route('applicantStage'))],
            'name' => [$required, 'string', 'max:255'],
            'service' => ['sometimes', Rule::in(BuiltInService::ALL)],
            'country_id' => ['nullable', 'exists:countries,id'],
            'university_id' => ['nullable', 'exists:universities,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_terminal' => ['sometimes', 'boolean'],
            'allowed_next_keys' => ['nullable', 'array'],
            'allowed_next_keys.*' => ['string', 'max:100'],
        ]);
        if ($partial) {
            // A stage belongs to its service: moving it to another service
            // would silently reparent every line currently standing on it.
            unset($data['service']);
        } elseif (empty($data['service'])) {
            $data['service'] = BuiltInService::SCHOLARSHIP;
        }

        return $data;
    }
}
