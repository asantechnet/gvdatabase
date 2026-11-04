<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicantContractResource;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Services\ApplicantStageTransitionGuard;
use App\Services\ContractTaskService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reads and moves one contracted service line.
 *
 * The line is the case: a scholarship case and a test offer on the same
 * applicant move independently, and the case payload never includes a sibling
 * line. The applicant's `current_stage_id` is kept mirrored to the most
 * recently changed line so aggregate screens (dashboards, reports, search)
 * continue to work, but it is never a case's editable stage.
 */
class ApplicantContractStageController extends Controller
{
    /**
     * One case: only this line's stage, history, delivery tasks and money.
     */
    public function show(Request $request, ApplicantContract $line)
    {
        $line->loadMissing(['applicant', 'currentStage', 'contract.parentContract', 'currency']);
        $applicant = $line->applicant;
        abort_unless($applicant, 404);

        $this->assertViewAccess($request, $applicant);

        $line->load([
            'stageHistory.oldStage',
            'stageHistory.newStage',
            'stageHistory.changer',
            'tasks' => fn ($query) => $query->orderBy('id'),
        ]);

        if ($request->user()?->hasPermission('view_applicant_financials')) {
            $line->load(['incomes' => fn ($query) => $query->orderByDesc('date')->orderByDesc('id')]);
        }

        $line->setAttribute('allowed_next_stages', $this->allowedNextStages($line)->all());

        return response()->json(['success' => true, 'data' => new ApplicantContractResource($line)]);
    }

    public function changeStage(Request $request, ApplicantContract $line)
    {
        $line->loadMissing(['applicant', 'currentStage', 'contract', 'currency']);
        $applicant = $line->applicant;
        abort_unless($applicant, 404);

        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        $this->assertManageAccess($request, $applicant);

        $data = $request->validate([
            'stage_id' => ['required', 'integer', 'exists:applicant_stages,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $next = ApplicantStage::findOrFail($data['stage_id']);
        // The stage must belong to the line's service. Legacy, service-agnostic
        // rows stay usable; another service's stage never is.
        $lineService = $line->serviceKey() ?? $applicant->service;
        abort_if($next->service && $lineService && $next->service !== $lineService, 422, 'business.applicant_stage_context_mismatch');

        $current = $line->currentStage;
        if ($current && ! $current->allowsTransitionTo($next)) {
            return response()->json([
                'message' => "Transition from {$current->name} to {$next->name} is not permitted.",
            ], 422);
        }

        $note = trim((string) ($data['note'] ?? '')) ?: null;
        // Closing a case needs a reason; moving it does not need a document.
        app(ApplicantStageTransitionGuard::class)->assertTransition($next, $note);

        DB::transaction(function () use ($line, $current, $next, $note, $user, $applicant): void {
            $line->update(['current_stage_id' => $next->id]);

            // Mirror before the history row: `ApplicantStageHistory::created`
            // re-syncs commission from the applicant's current stage, and a
            // stale mirror would value a just-completed case as still open.
            $applicant->update([
                'current_stage_id' => $next->id,
                'status' => $next->is_terminal ? 'closed' : 'active',
            ]);

            ApplicantStageHistory::create([
                'applicant_id' => $applicant->id,
                'applicant_contract_id' => $line->id,
                'old_stage_id' => $current?->id,
                'new_stage_id' => $next->id,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'note' => $note,
            ]);

            app(ContractTaskService::class)->syncForStage($line->fresh(), $next, $user);
        });

        return response()->json([
            'success' => true,
            'data' => new ApplicantContractResource($line->fresh()->load(['currentStage', 'currency', 'contract'])),
        ]);
    }

    /**
     * The legal next stages for this case, or the whole service list when the
     * current stage is missing or no longer in the live catalogue.
     */
    private function allowedNextStages(ApplicantContract $line)
    {
        $service = $line->serviceKey() ?? $line->applicant?->service;

        $stages = ApplicantStage::query()
            ->where('is_active', true)
            ->when($service, fn ($query) => $query->where('service', $service))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique('key')
            ->values();

        $current = $line->currentStage;
        $currentIsListed = $current && $stages->contains(
            fn (ApplicantStage $stage) => $stage->id === $current->id || $stage->key === $current->key,
        );

        $next = $current && $currentIsListed
            ? $stages->filter(fn (ApplicantStage $stage) => in_array($stage->key, $current->allowed_next_keys ?? [], true))->values()
            : $stages;

        return $next->map(fn (ApplicantStage $stage) => [
            'id' => $stage->id,
            'key' => $stage->key,
            'name' => $stage->name,
            'service' => $stage->service,
            'sort_order' => (int) $stage->sort_order,
            'is_terminal' => (bool) $stage->is_terminal,
            'allowed_next_keys' => $stage->allowed_next_keys ?? [],
        ]);
    }

    /** Reading a case follows the same ownership scope as the applicant page. */
    private function assertViewAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }
        abort_unless(app(EmployeeScope::class)->canViewOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }

    /** Moving a case needs the manage scope the line writer has always used. */
    private function assertManageAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }
        abort_unless(app(EmployeeScope::class)->canManageOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }
}
