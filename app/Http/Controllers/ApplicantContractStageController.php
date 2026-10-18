<?php

namespace App\Http\Controllers;

use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Services\ContractTaskService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Moves one contracted service line through its service's stage list.
 *
 * The line owns the workflow now (a scholarship case and a test offer on the
 * same applicant move independently). The applicant's `current_stage_id` is
 * kept mirrored to the most recently changed line so the existing aggregate
 * screens (dashboards, reports, search) continue to work.
 */
class ApplicantContractStageController extends Controller
{
    public function changeStage(Request $request, ApplicantContract $line)
    {
        $line->loadMissing(['applicant', 'currentStage', 'contract', 'currency']);
        $applicant = $line->applicant;
        abort_unless($applicant, 404);

        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if (! $user->isSuperAdmin() && ! $user->hasPermission('view_all_applicants')) {
            abort_unless(app(EmployeeScope::class)->canManageOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
        }

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

        DB::transaction(function () use ($line, $current, $next, $data, $user, $applicant): void {
            $line->update(['current_stage_id' => $next->id]);

            ApplicantStageHistory::create([
                'applicant_id' => $applicant->id,
                'applicant_contract_id' => $line->id,
                'old_stage_id' => $current?->id,
                'new_stage_id' => $next->id,
                'changed_by' => $user->id,
                'changed_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            // Mirror the line's stage onto the applicant for the aggregate views.
            $applicant->update([
                'current_stage_id' => $next->id,
                'status' => $next->is_terminal ? 'closed' : 'active',
            ]);

            app(ContractTaskService::class)->syncForStage($line->fresh(), $next, $user);
        });

        return response()->json([
            'success' => true,
            'data' => $line->fresh()->load(['currentStage', 'currency', 'contract']),
        ]);
    }
}
