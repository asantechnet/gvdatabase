<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditPresenter;
use App\Support\AuditRegistry;
use App\Support\EmployeeScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Record-level audit state for registers.
 *
 * One bounded request answers "what was the latest audit entry for each of
 * these visible rows", so a table can draw its Created/Updated/Deleted rail
 * without a per-row audit fetch. The read reuses the same permission and team
 * scope as the Audits page; a caller never receives a trail for a row whose
 * audit they could not open.
 */
class AuditTrailController extends Controller
{
    private const SETTINGS_KEYS = ['account_deactivations', 'audit_settings'];

    public function index(Request $request, AuditPresenter $presenter): JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_audit_logs'), 403, 'auth.forbidden');

        $data = $request->validate([
            'resource' => ['required', 'string', 'max:80', Rule::in([...array_keys(AuditRegistry::resources()), ...self::SETTINGS_KEYS])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $class = AuditRegistry::resources()[$data['resource']] ?? null;

        // The two audit-settings keys have no auditable record model under this
        // registry; their own history remains available on the Audits page.
        if ($class === null) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        // Latest visible audit per record in one grouped query. Applying the
        // actor scope inside the aggregate keeps a team reviewer from learning
        // that a newer, invisible entry exists.
        $latestIds = AuditLog::query()
            ->selectRaw('MAX(id) as id, auditable_id')
            ->whereIn('auditable_id', $ids)
            ->where(function ($query) use ($class, $data): void {
                $query->where('subject_type', $data['resource'])
                    // Legacy rows predate `subject_type`; resolve them through
                    // the registry model's exact auditable type.
                    ->orWhere(function ($legacy) use ($class): void {
                        $legacy->whereNull('subject_type')
                            ->where('auditable_type', $class);
                    });
            })
            ->tap(fn ($query) => $this->scopeToActor($query, $request->user()))
            ->groupBy('auditable_id')
            ->pluck('id');

        $rows = AuditLog::query()
            ->with(['user:id,name,email', 'employee:id,name'])
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy(fn (AuditLog $audit) => (int) $audit->auditable_id);

        $trails = collect($ids)
            ->map(function (int $id) use ($rows, $presenter): ?array {
                $audit = $rows->get($id);

                if (! $audit) {
                    return null;
                }

                return ['id' => $id, ...$presenter->presentTrail($audit)];
            })
            ->filter()
            ->values();

        return response()->json(['success' => true, 'data' => $trails]);
    }

    /**
     * Mirrors AuditLogController::scopeToActor. Kept local because the review
     * list is a read-only dependency of this task and must not be edited.
     */
    private function scopeToActor($query, User $user): void
    {
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_audits')) {
            return;
        }

        if (! $user->hasPermission('view_team_audit_logs')) {
            return;
        }

        $employeeIds = app(EmployeeScope::class)->visibleEmployeeIds($user);
        $query->where(function ($query) use ($employeeIds, $user): void {
            $query->whereIn('employee_id', $employeeIds)
                ->orWhere('user_id', $user->id);
        });
    }
}
