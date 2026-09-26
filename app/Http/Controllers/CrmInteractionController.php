<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrmInteractionResource;
use App\Models\Applicant;
use App\Models\CrmInteraction;
use App\Models\Lead;
use App\Models\Visitor;
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
            'lead' => Lead::findOrFail($id),
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
            'visitor' => Visitor::findOrFail($id), 'lead' => Lead::findOrFail($id), 'applicant' => Applicant::findOrFail($id), default => abort(404)
        };
        $data = $request->validate(['channel' => ['required', Rule::in(['call', 'message', 'email', 'meeting', 'note'])], 'title' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:10000'], 'contacted_at' => ['nullable', 'date'], 'follow_up_at' => ['nullable', 'date', 'after_or_equal:contacted_at']]);
        $interaction = $model->interactions()->create([...$data, 'contacted_at' => $data['contacted_at'] ?? now(), 'created_by' => $request->user()->id]);

        return new CrmInteractionResource($interaction->load('creator'));
    }

    public function update(Request $request, CrmInteraction $interaction): CrmInteractionResource
    {
        $this->authorizeInteractionMutation($request, $interaction);
        $data = $request->validate(['channel' => ['sometimes', Rule::in(['call', 'message', 'email', 'meeting', 'note'])],
            'title' => ['sometimes', 'required', 'string', 'max:255'], 'notes' => ['sometimes', 'required', 'string', 'max:10000'],
            'contacted_at' => ['sometimes', 'date'], 'follow_up_at' => ['nullable', 'date']]);
        $interaction->update($data);

        return new CrmInteractionResource($interaction->load('creator'));
    }

    public function destroy(Request $request, CrmInteraction $interaction)
    {
        $this->authorizeInteractionMutation($request, $interaction);
        $interaction->delete();

        return response()->noContent();
    }

    private function authorizeInteractionMutation(Request $request, CrmInteraction $interaction): void
    {
        $type = match ($interaction->contactable_type) {
            (new Applicant)->getMorphClass(), Applicant::class => 'applicant',
            (new Visitor)->getMorphClass(), Visitor::class => 'visitor',
            (new Lead)->getMorphClass(), Lead::class => 'lead',
            default => abort(404),
        };

        $this->authorizeMutation($request, $type);
    }

    private function authorizeMutation(Request $request, string $type): void
    {
        $permission = $type === 'applicant' ? 'edit_applicants' : 'edit_visitors';
        abort_unless($request->user()?->hasPermission($permission), 403, 'auth.forbidden');
    }
}
