<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrmInteractionResource;
use App\Models\Applicant;
use App\Models\Country;
use App\Models\CrmInteraction;
use App\Models\University;
use App\Models\Visitor;
use App\Support\EmployeeScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmInteractionController extends Controller
{
    public function updateNotes(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeMutation($request, $type);
        $model = match ($type) {
            'visitor' => Visitor::findOrFail($id),
            'applicant' => Applicant::findOrFail($id),
            default => abort(404),
        };
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $model->update(['notes' => $data['notes'] ?? null]);

        return response()->json(['success' => true, 'data' => ['notes' => $model->notes]]);
    }

    public function store(Request $request, string $type, int $id): CrmInteractionResource
    {
        $this->authorizeMutation($request, $type);
        $model = match ($type) {
            'visitor' => Visitor::findOrFail($id), 'applicant' => Applicant::findOrFail($id), default => abort(404)
        };
        $data = $request->validate(['channel' => ['required', Rule::in(['call', 'message', 'email', 'meeting', 'note'])], 'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'], 'contacted_at' => ['nullable', 'date'], 'follow_up_at' => ['nullable', 'date', 'after_or_equal:contacted_at']]);
        $interaction = $model->interactions()->create([...$data, 'contacted_at' => $data['contacted_at'] ?? now(), 'created_by' => $request->user()->id]);

        return new CrmInteractionResource($interaction->load('creator'));
    }

    public function update(Request $request, CrmInteraction $interaction): CrmInteractionResource
    {
        $this->authorizeExistingInteraction($request, $interaction);
        $data = $request->validate(['channel' => ['sometimes', Rule::in(['call', 'message', 'email', 'meeting', 'note'])],
            'title' => ['sometimes', 'required', 'string', 'max:255'], 'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'contacted_at' => ['sometimes', 'date'], 'follow_up_at' => ['nullable', 'date']]);
        $interaction->update($data);

        return new CrmInteractionResource($interaction->load('creator'));
    }

    public function destroy(Request $request, CrmInteraction $interaction): JsonResponse
    {
        $this->authorizeExistingInteraction($request, $interaction);
        $interaction->delete();

        return response()->noContent();
    }

    private function authorizeExistingInteraction(Request $request, CrmInteraction $interaction): void
    {
        if ($interaction->applicant_id) {
            $applicant = Applicant::query()->findOrFail($interaction->applicant_id);
            $user = $request->user();
            abort_unless($user && ($user->hasPermission('edit_applicants') || $user->isSuperAdmin()), 403, 'auth.forbidden');
            abort_unless($user->isSuperAdmin()
                || app(EmployeeScope::class)->canManageOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
            $this->assertTargetLinked($applicant, $interaction);

            return;
        }

        $this->authorizeMutation($request, $this->typeOf($interaction));
    }

    private function assertTargetLinked(Applicant $applicant, CrmInteraction $interaction): void
    {
        $linked = match (true) {
            $interaction->contactable instanceof Country => (int) $applicant->detail()?->country_id === (int) $interaction->contactable_id,
            $interaction->contactable instanceof University => (int) $applicant->detail()?->university_id === (int) $interaction->contactable_id
                || $applicant->universities()->where('university_id', $interaction->contactable_id)->exists(),
            default => false,
        };

        abort_unless($linked, 422, 'business.relationship_not_linked_to_case');
    }

    private function typeOf(CrmInteraction $interaction): string
    {
        return match (true) {
            $interaction->contactable instanceof Applicant => 'applicant',
            $interaction->contactable instanceof Visitor => 'visitor',
            $interaction->contactable instanceof Country => 'country',
            $interaction->contactable instanceof University => 'university',
            default => abort(404),
        };
    }

    private function authorizeMutation(Request $request, string $type): void
    {
        $permission = match ($type) {
            'applicant' => 'edit_applicants',
            'visitor' => 'edit_visitors',
            'country' => 'edit_country',
            'university' => 'edit_university',
            default => abort(404),
        };
        abort_unless($request->user()?->hasPermission($permission) || $request->user()?->isSuperAdmin(), 403, 'auth.forbidden');
    }
}
