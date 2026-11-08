<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrmInteractionResource;
use App\Http\Resources\CrmRelationshipResource;
use App\Models\Applicant;
use App\Models\Country;
use App\Models\CrmInteraction;
use App\Models\University;
use App\Models\Visitor;
use App\Support\EmployeeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmRelationshipController extends Controller
{
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->findRecord($type, $id);
        $applicant = $this->caseApplicant($request);

        if ($applicant) {
            $this->assertLinkedTarget($applicant, $record, $type);
            $record->load([
                'interactions' => fn ($query) => $query
                    ->where('applicant_id', $applicant->id)
                    ->with(['creator', 'applicant:id,name'])
                    ->orderByDesc('contacted_at')
                    ->orderByDesc('id'),
                ...($type === 'university' ? ['country:id,name'] : []),
            ]);

            return response()->json([
                'success' => true,
                'data' => new CrmRelationshipResource($record, $type, true),
            ]);
        }

        $this->authorizeRead($request, $type);
        $record->load([
            'interactions' => fn ($query) => $query->with(['creator', 'applicant:id,name'])
                ->orderByDesc('contacted_at')
                ->orderByDesc('id'),
            ...($type === 'university' ? ['country:id,name'] : []),
        ]);

        return response()->json([
            'success' => true,
            'data' => new CrmRelationshipResource($record, $type),
        ]);
    }

    public function updateNotes(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->findRecord($type, $id);
        $this->authorizeWrite($request, $type);
        abort_if($request->filled('applicant_id'), 422, 'business.relationship_not_linked_to_case');

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $record->update(['notes' => $data['notes'] ?? null]);

        return response()->json(['success' => true, 'data' => ['notes' => $record->notes]]);
    }

    public function storeInteraction(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->findRecord($type, $id);
        $data = $request->validate($this->interactionRules());
        $applicantId = $data['applicant_id'] ?? null;
        unset($data['applicant_id']);

        if ($applicantId) {
            $applicant = $this->authorizeCaseManage($request, (int) $applicantId);
            $this->assertLinkedTarget($applicant, $record, $type);
        } else {
            $this->authorizeWrite($request, $type);
        }

        $interaction = $record->interactions()->create([
            ...$data,
            'applicant_id' => $applicantId,
            'contacted_at' => $data['contacted_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => new CrmInteractionResource($interaction->load(['creator', 'applicant:id,name'])),
        ], 201);
    }

    /**
     * The case relationship card: only the selected country and attached
     * universities, and only interactions carrying this applicant id.
     */
    public function applicantRecords(Request $request, Applicant $applicant): JsonResponse
    {
        $this->authorizeCaseView($request, $applicant);

        $applicant->load([
            'scholarshipDetail.country',
            'scholarshipDetail.university',
            'privateDetail.country',
            'privateDetail.university',
            'universities.university',
        ]);

        $detail = $applicant->detail();
        $country = $detail?->country;
        $attached = $applicant->universities
            ->filter(fn ($row) => $row->university !== null)
            ->values();
        $universityIds = $attached->pluck('university_id')
            ->when($detail?->university_id, fn ($ids, $id) => $ids->push($id))
            ->unique()
            ->values()
            ->all();

        $typeCountry = (new Country)->getMorphClass();
        $typeUniversity = (new University)->getMorphClass();

        $records = CrmInteraction::query()
            ->where('applicant_id', $applicant->id)
            ->where(function ($query) use ($country, $universityIds, $typeCountry, $typeUniversity): void {
                if ($country) {
                    $query->orWhere(fn ($scope) => $scope
                        ->where('contactable_type', $typeCountry)
                        ->where('contactable_id', $country->id));
                }
                if ($universityIds !== []) {
                    $query->orWhere(fn ($scope) => $scope
                        ->where('contactable_type', $typeUniversity)
                        ->whereIn('contactable_id', $universityIds));
                }
            })
            ->with('creator:id,name')
            ->orderByDesc('contacted_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (CrmInteraction $interaction) use ($country, $attached): array {
                $isCountry = $interaction->contactable_type === (new Country)->getMorphClass();
                $universityRow = $isCountry ? null : $attached->firstWhere('university_id', $interaction->contactable_id);

                return [
                    'id' => $interaction->id,
                    'target_type' => $isCountry ? 'country' : 'university',
                    'target_id' => $interaction->contactable_id,
                    'target_name' => $isCountry ? $country?->name : $universityRow?->university?->name,
                    'channel' => $interaction->channel,
                    'title' => $interaction->title,
                    'notes' => $interaction->notes,
                    'contacted_at' => $interaction->contacted_at?->toISOString(),
                    'follow_up_at' => $interaction->follow_up_at?->toISOString(),
                    'creator' => $interaction->creator ? [
                        'id' => $interaction->creator->id,
                        'name' => $interaction->creator->name,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'applicant_id' => $applicant->id,
                'country' => $country ? [
                    'id' => $country->id,
                    'name' => $country->name,
                    'code' => $country->code,
                    'contact' => $country->contact,
                    'email' => $country->email,
                ] : null,
                'universities' => $attached->map(fn ($row) => [
                    'id' => $row->id,
                    'university_id' => $row->university_id,
                    'status' => $row->status,
                    'intake' => $row->intake,
                    'university' => $row->university ? [
                        'id' => $row->university->id,
                        'name' => $row->university->name,
                        'contact' => $row->university->contact,
                        'email' => $row->university->email,
                    ] : null,
                ])->values(),
                'records' => $records,
            ],
        ]);
    }

    private function findRecord(string $type, int $id): Model
    {
        return match ($type) {
            'visitor' => Visitor::query()->findOrFail($id),
            'applicant' => Applicant::query()->findOrFail($id),
            'university' => University::query()->findOrFail($id),
            'country' => Country::query()->findOrFail($id),
            default => abort(404),
        };
    }

    private function caseApplicant(Request $request): ?Applicant
    {
        if (! $request->filled('applicant_id')) {
            return null;
        }

        $data = $request->validate([
            'applicant_id' => ['integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
        ]);
        $applicant = Applicant::query()->findOrFail($data['applicant_id']);
        $this->authorizeCaseView($request, $applicant);

        return $applicant;
    }

    /** A case target must be the selected country or an attached university. */
    private function assertLinkedTarget(Applicant $applicant, Model $record, string $type): void
    {
        $linked = match ($type) {
            'country' => (int) $applicant->detail()?->country_id === (int) $record->getKey(),
            'university' => (int) $applicant->detail()?->university_id === (int) $record->getKey()
                || $applicant->universities()->where('university_id', $record->getKey())->exists(),
            default => false,
        };

        abort_unless($linked, 422, 'business.relationship_not_linked_to_case');
    }

    private function authorizeRead(Request $request, string $type): void
    {
        $permission = match ($type) {
            'applicant' => 'view_applicants',
            'visitor' => 'view_visitors',
            'university' => 'view_university',
            'country' => 'view_country',
        };
        abort_unless($request->user()?->hasPermission($permission) || $request->user()?->isSuperAdmin(), 403, 'auth.forbidden');
    }

    private function authorizeWrite(Request $request, string $type): void
    {
        $permission = match ($type) {
            'applicant' => 'edit_applicants',
            'visitor' => 'edit_visitors',
            'university' => 'edit_university',
            'country' => 'edit_country',
        };
        abort_unless($request->user()?->hasPermission($permission) || $request->user()?->isSuperAdmin(), 403, 'auth.forbidden');
    }

    private function authorizeCaseView(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('view_applicants')
            || $user->hasPermission('view_all_applicants')
            || $user->isSuperAdmin()), 403, 'auth.forbidden');
        abort_unless($user->isSuperAdmin()
            || $user->hasPermission('view_all_applicants')
            || app(EmployeeScope::class)->canViewOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }

    private function authorizeCaseManage(Request $request, int $applicantId): Applicant
    {
        $applicant = Applicant::query()->findOrFail($applicantId);
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('edit_applicants') || $user->isSuperAdmin()), 403, 'auth.forbidden');
        abort_unless($user->isSuperAdmin()
            || app(EmployeeScope::class)->canManageOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');

        return $applicant;
    }

    /** @return array<string, mixed> */
    private function interactionRules(): array
    {
        return [
            'channel' => ['required', Rule::in(['call', 'message', 'email', 'meeting', 'note'])],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'contacted_at' => ['nullable', 'date'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:contacted_at'],
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
        ];
    }
}
