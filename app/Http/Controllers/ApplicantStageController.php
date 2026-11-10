<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\University;
use App\Services\ApplicantStageContext;
use App\Support\BuiltInService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicantStageController extends Controller
{
    /** Services whose stages carry a destination country or university scope. */
    private const ADMISSION_SERVICES = [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE];

    public function __construct(private readonly ApplicantStageContext $context) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'service' => ['nullable', Rule::in(BuiltInService::enabled())],
            'country_id' => ['nullable', 'exists:countries,id'],
            'university_id' => ['nullable', 'exists:universities,id'],
            'line_id' => ['nullable', 'integer', Rule::exists('applicant_contract', 'id')->whereNull('deleted_at')],
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
            'active' => ['nullable', 'boolean'],
            'all' => ['nullable', 'boolean'],
        ]);
        $service = $data['service'] ?? null;
        if ($service && BuiltInService::isCaseFree($service)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // A case read resolves the service from the case itself. The response is
        // the same stage rows the case may move to, never applicant data.
        $case = $this->caseContext($data, $service);
        if ($case) {
            [$applicant, $caseService] = $case;
            if (BuiltInService::isCaseFree($caseService)) {
                return response()->json(['success' => true, 'data' => []]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->context->stagesFor($applicant, $caseService)->values(),
            ]);
        }

        $query = ApplicantStage::query()
            // Only case-bearing services have workflows; Academy is the one
            // service sold without a case.
            ->whereIn('service', BuiltInService::CASE_BEARING)
            ->when($service, fn ($q) => $q->where('service', $service));
        // Stage pickers only offer live stages; `all=1` is reserved for the
        // stage editor, which must also show inactive rows.
        if (array_key_exists('active', $data)) {
            $query->where('is_active', (bool) $data['active']);
        } elseif (! ($data['all'] ?? false)) {
            $query->where('is_active', true);
        }

        $all = (bool) ($data['all'] ?? false);

        // Destination scope only exists for the admission services. Test-offer
        // and academy rows never carry one, so filtering them here would only
        // hide stages for no reason.
        if (! $all && ($service === null || in_array($service, self::ADMISSION_SERVICES, true))) {
            $countryId = $data['country_id'] ?? null;
            $universityId = $data['university_id'] ?? null;
            // A university implies its country: a case at that university also
            // belongs to its destination country's stage scope.
            if (! $countryId && $universityId) {
                $countryId = University::query()->whereKey($universityId)->value('country_id');
            }
            $query->where(function ($q) use ($countryId, $universityId) {
                $q->whereNull('country_id')->whereNull('university_id');
                if ($countryId) {
                    $q->orWhere(fn ($stage) => $stage->where('country_id', $countryId)->whereNull('university_id'));
                }
                if ($universityId) {
                    $q->orWhere('university_id', $universityId);
                }
            });
        }

        $stages = $query->orderBy('sort_order')->get();
        if (! $service) {
            $stages = $stages->unique('key')->values();
        }

        return response()->json(['success' => true, 'data' => $stages]);
    }

    public function store(Request $request)
    {
        $this->assertTerminalManaged($request);

        $data = $this->validated($request);
        $data['key'] = $this->uniqueStageKey($data['service'], $data['key'] ?? null, $data['name']);

        return response()->json([
            'success' => true,
            'data' => ApplicantStage::create($data),
        ], 201);
    }

    public function update(Request $request, ApplicantStage $applicantStage)
    {
        // The lock is checked before validation so a frozen row always answers
        // 409 instead of 422, whatever the body contains.
        $this->assertUnlocked($applicantStage);
        $this->assertTerminalManaged($request);
        $applicantStage->update($this->validated($request, true));

        return response()->json(['success' => true, 'data' => $applicantStage->fresh()]);
    }

    public function destroy(ApplicantStage $applicantStage)
    {
        abort_if(BuiltInService::isCaseFree($applicantStage->service), 422, 'business.applicant_stage_service_not_supported');
        $this->assertUnlocked($applicantStage);
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

    private function assertUnlocked(ApplicantStage $stage): void
    {
        abort_if($stage->isLocked(), 409, 'business.applicant_stage_locked');
    }

    /** `is_terminal` is system-owned; even an explicit false is not accepted. */
    private function assertTerminalManaged(Request $request): void
    {
        if ($request->exists('is_terminal')) {
            throw ValidationException::withMessages(['is_terminal' => ['business.applicant_stage_terminal_managed']]);
        }
    }

    /**
     * Resolve `line_id`/`applicant_id` to the applicant and the small service
     * context a case-scoped read needs.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Applicant, 1: string}|null
     */
    private function caseContext(array $data, ?string $service): ?array
    {
        if (! empty($data['line_id'])) {
            $line = ApplicantContract::query()->with('applicant')->findOrFail($data['line_id']);
            abort_unless($line->applicant, 404);

            return [$line->applicant, $line->serviceKey() ?? $line->applicant->service];
        }

        if (! empty($data['applicant_id'])) {
            $applicant = Applicant::query()->findOrFail($data['applicant_id']);

            return [$applicant, $service ?? $applicant->service];
        }

        return null;
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $namePresence = $partial ? 'sometimes' : 'required';
        // An existing stage is normalized against its own service: a body can
        // never reparent it or leak another service's scope into it.
        $service = $partial
            ? $request->route('applicantStage')?->service
            : $request->input('service', BuiltInService::SCHOLARSHIP);
        abort_unless(BuiltInService::isCaseBearing($service), 422, 'business.applicant_stage_service_not_supported');
        $usesAdmissionScope = in_array($service, self::ADMISSION_SERVICES, true);

        $data = $request->validate([
            // The code is derived from the name when omitted. Creation makes
            // it unique inside the service (including soft-deleted rows).
            'key' => ['sometimes', 'nullable', 'string', 'max:100', 'alpha_dash'],
            'name' => [$namePresence, 'string', 'max:255'],
            'service' => ['sometimes', Rule::in(BuiltInService::enabled())],
            // Values outside the stage's service are accepted and normalized to
            // null below, never copied into another workflow.
            'country_id' => $usesAdmissionScope ? ['nullable', 'exists:countries,id'] : ['nullable', 'integer'],
            'university_id' => $usesAdmissionScope ? ['nullable', 'exists:universities,id'] : ['nullable', 'integer'],
            'test_id' => ['prohibited'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'allowed_next_keys' => ['nullable', 'array'],
            'allowed_next_keys.*' => ['string', 'max:100'],
        ]);
        if ($partial) {
            // A stage belongs to its service: moving it to another service
            // would silently reparent every line currently standing on it.
            // Its key is equally immutable: allowed-next edges and case-win
            // history refer to it by string.
            unset($data['service'], $data['key']);
        } elseif (empty($data['service'])) {
            $data['service'] = BuiltInService::SCHOLARSHIP;
        }

        return $this->normalizeScope($data, $service);
    }

    /**
     * A unique stage key inside one service.
     *
     * The client normally sends a key derived from the name. If it is missing,
     * derive it from the name here. If the candidate is already taken —
     * including by a soft-deleted row, which the composite index still
     * reserves — add the next numeric suffix.
     */
    private function uniqueStageKey(string $service, ?string $key, string $name): string
    {
        $base = trim((string) $key);
        if ($base === '') {
            $base = Str::slug($name, '_');
        }
        $base = $base !== '' ? $base : 'stage';
        $base = substr($base, 0, 100);

        $candidate = $base;
        $suffix = 2;
        while (ApplicantStage::withTrashed()->where('service', $service)->where('key', $candidate)->exists()) {
            $suffixText = '_'.$suffix++;
            $candidate = substr($base, 0, 100 - strlen($suffixText)).$suffixText;
        }

        return $candidate;
    }

    /**
     * Keep only the scope columns the service actually uses.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeScope(array $data, ?string $service): array
    {
        if ($service !== null && in_array($service, self::ADMISSION_SERVICES, true)) {
            return $data;
        }

        $data['country_id'] = null;
        $data['university_id'] = null;

        return $data;
    }
}
