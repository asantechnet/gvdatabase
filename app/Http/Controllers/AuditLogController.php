<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Support\AuditPresenter;
use App\Support\EmployeeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditPresenter $presenter): JsonResponse
    {
        $this->authorizePermission($request, 'view_audit_logs');
        $data = $request->validate([
            'resource' => ['nullable', 'string', 'max:80'],
            'event' => ['nullable', 'string', 'max:64'],
            'audit_state' => ['nullable', 'in:pending,audited'],
            'resolution' => ['nullable', 'in:pending,approved,discarded'],
            'changed_by' => ['nullable', 'integer'],
            'audited_by' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', 'in:created_at,audited_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = AuditLog::query()->with(['user:id,name,email', 'employee:id,name', 'auditor:id,name,email'])
            ->when(isset($data['resource']), fn ($query) => $query->where('subject_type', $data['resource']))
            ->when(isset($data['event']), fn ($query) => $query->where('event', $data['event']))
            ->when(($data['audit_state'] ?? null) === 'pending', fn ($query) => $query->whereNull('audited_at'))
            ->when(($data['audit_state'] ?? null) === 'audited', fn ($query) => $query->whereNotNull('audited_at'))
            ->when(isset($data['resolution']), fn ($query) => $query->where('review_status', $data['resolution']))
            ->when(isset($data['changed_by']), fn ($query) => $query->where('user_id', $data['changed_by']))
            ->when(isset($data['audited_by']), fn ($query) => $query->where('audited_by', $data['audited_by']))
            ->when(isset($data['from']), fn ($query) => $query->whereDate('created_at', '>=', $data['from']))
            ->when(isset($data['to']), fn ($query) => $query->whereDate('created_at', '<=', $data['to']))
            ->when(isset($data['search']), function ($query) use ($data) {
                $search = '%'.addcslashes($data['search'], '%_\\').'%';
                $query->where(function ($nested) use ($search) {
                    $nested->where('subject_label', 'like', $search)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $search))
                        ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', $search))
                        ->orWhereHas('auditor', fn ($auditor) => $auditor->where('name', 'like', $search));
                });
            });

        $this->scopeToActor($query, $request->user());

        $paginator = $query
            ->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 25);
        $paginator->setCollection($paginator->getCollection()->map(fn (AuditLog $audit) => $presenter->present($audit)));

        return response()->json($paginator);
    }

    public function pendingCount(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'view_audit_logs');

        return response()->json(['data' => ['count' => AuditLog::query()->whereNull('audited_at')->count()]]);
    }

    public function deleted(Request $request, AuditPresenter $presenter): JsonResponse
    {
        $request->merge(['event' => 'deleted']);

        return $this->index($request, $presenter);
    }

    /**
     * Real-time audit delivery, mirroring the notification heartbeat.
     *
     * The flag is raised when an audit row is written. Only while the flag is
     * set does this endpoint read rows, and it advances the user's id cursor
     * and clears the flag inside a row-locked transaction so a batch is handed
     * out exactly once even under overlapping polls.
     */
    public function heartbeat(Request $request, AuditPresenter $presenter): JsonResponse
    {
        $this->authorizePermission($request, 'view_audit_logs', 'view_team_audit_logs');

        $rows = DB::transaction(function () use ($request) {
            $locked = User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->unread_audit_logs) {
                return collect();
            }

            $query = AuditLog::query()->with(['user:id,name,email', 'employee:id,name', 'auditor:id,name,email']);
            $this->scopeToActor($query, $locked);
            $rows = $query->where('id', '>', (int) ($locked->audit_logs_seen_id ?? 0))
                ->orderBy('id')
                ->limit(20)
                ->get();

            $locked->forceFill([
                'unread_audit_logs' => false,
                'audit_logs_seen_at' => now(),
                'audit_logs_seen_id' => $rows->max('id') ?? $locked->audit_logs_seen_id,
            ])->saveQuietly();

            return $rows;
        });

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (AuditLog $audit) => $presenter->present($audit))->values(),
        ]);
    }

    public function show(Request $request, AuditLog $auditLog, AuditPresenter $presenter): JsonResponse
    {
        $this->authorizePermission($request, 'view_audit_logs');
        $this->assertAuditAccess($request->user(), $auditLog);

        return response()->json(['success' => true, 'data' => $presenter->present($auditLog)]);
    }

    public function revert(Request $request, AuditLog $auditLog, AuditPresenter $presenter): JsonResponse
    {
        $this->authorizePermission($request, 'revert_audit_changes');
        $model = $this->discard($auditLog);
        $this->markAudited($auditLog, $request->user()->id, 'discarded');

        return response()->json([
            'success' => true,
            'message' => 'Change reverted and retained in the audit history.',
            'data' => $presenter->present($auditLog->fresh()),
            'record' => $model->fresh(),
        ]);
    }

    public function review(Request $request, AuditLog $auditLog, AuditPresenter $presenter): JsonResponse
    {
        $this->authorizePermission($request, 'review_audit_logs');
        $data = $request->validate(['decision' => ['required', 'in:approved,discarded']]);
        abort_if($auditLog->audited_at, 409, 'business.audit_already_reviewed');

        if ($data['decision'] === 'discarded') {
            $this->discard($auditLog);
        }
        $this->markAudited($auditLog, $request->user()->id, $data['decision']);

        return response()->json([
            'success' => true,
            'message' => $data['decision'] === 'approved'
                ? 'Change kept and retained in the audit history.'
                : 'Change reverted and retained in the audit history.',
            'data' => $presenter->present($auditLog->fresh()),
        ]);
    }

    /** Review a deliberate, page-scoped selection without partial results. */
    public function bulkReview(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'review_audit_logs');
        $data = $request->validate([
            'audit_ids' => ['required', 'array', 'min:1', 'max:100'],
            'audit_ids.*' => ['required', 'integer', 'distinct', 'exists:audit_logs,id'],
            'decision' => ['required', 'in:approved,discarded'],
        ]);

        DB::transaction(function () use ($data, $request) {
            // Undo newest-first so selecting consecutive changes to one record
            // remains safe. One invalid row rolls back the entire batch.
            $audits = AuditLog::query()
                ->whereIn('id', $data['audit_ids'])
                ->lockForUpdate()
                ->orderBy('id', $data['decision'] === 'discarded' ? 'desc' : 'asc')
                ->get();

            abort_unless($audits->count() === count($data['audit_ids']), 404, 'resource.not_found');
            abort_if($audits->contains(fn (AuditLog $audit) => $audit->audited_at), 409, 'business.audit_already_reviewed');

            foreach ($audits as $audit) {
                if ($data['decision'] === 'discarded') {
                    abort_unless(in_array($audit->event, ['updated', 'deleted'], true), 422, 'business.audit_event_not_revertible');
                    $this->discard($audit);
                }
                $this->markAudited($audit, $request->user()->id, $data['decision']);
            }
        });

        return response()->json(['success' => true, 'data' => ['count' => count($data['audit_ids'])]]);
    }

    private function markAudited(AuditLog $auditLog, int $userId, string $resolution): void
    {
        $auditLog->forceFill([
            'review_status' => $resolution,
            'audited_by' => $userId,
            'audited_at' => now(),
            // Keep legacy readers correct during the transition.
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ])->save();
    }

    private function discard(AuditLog $auditLog): Model
    {
        return DB::transaction(function () use ($auditLog) {
            $auditLog->refresh();
            $model = $this->auditedModel($auditLog);
            abort_if($auditLog->audited_at, 409, 'business.audit_already_reviewed');
            abort_if(
                AuditLog::query()
                    ->where('auditable_type', $auditLog->auditable_type)
                    ->where('auditable_id', $auditLog->auditable_id)
                    ->where('id', '>', $auditLog->id)
                    // A later audit that was itself discarded represents a
                    // change already rolled back. It must not prevent a
                    // newest-first bulk rollback from reaching the preceding
                    // selected change; approved and pending changes still do.
                    ->where(fn ($query) => $query->whereNull('review_status')->orWhere('review_status', '!=', 'discarded'))
                    ->exists(),
                409,
                'business.audit_not_latest_change'
            );

            if ($auditLog->event === 'deleted') {
                abort_unless($model->trashed(), 409, 'business.audit_row_not_deleted');
                Model::withoutEvents(fn () => $model->restore());
            } elseif ($auditLog->event === 'updated') {
                abort_if($model->trashed(), 409, 'business.audit_row_deleted');
                Model::withoutEvents(fn () => $model->forceFill($auditLog->old_values ?? [])->save());
            } else {
                abort(422, 'business.audit_event_not_revertible');
            }

            return $model;
        });
    }

    private function auditedModel(AuditLog $auditLog): Model
    {
        $class = $auditLog->auditable_type;
        abort_unless(class_exists($class) && is_subclass_of($class, Model::class), 422, 'business.audit_model_invalid');
        abort_unless(in_array(Auditable::class, class_uses_recursive($class), true), 422, 'business.audit_event_not_revertible');

        $model = $class::withTrashed()->find($auditLog->auditable_id);
        abort_unless($model, 404, 'resource.not_found');

        return $model;
    }

    private function authorizePermission(Request $request, string ...$permissions): void
    {
        abort_unless(
            collect($permissions)->contains(fn (string $permission) => $request->user()->hasPermission($permission)),
            403,
            'auth.forbidden',
        );
    }

    /**
     * A team reviewer sees only the audit rows produced by their group (plus
     * their own actions). Organisation-wide review needs `view_all_audits`.
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

    private function assertAuditAccess(User $user, AuditLog $auditLog): void
    {
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_audits')) {
            return;
        }

        if (! $user->hasPermission('view_team_audit_logs')) {
            return;
        }

        $employeeIds = app(EmployeeScope::class)->visibleEmployeeIds($user);
        abort_unless(
            (int) $auditLog->user_id === (int) $user->id
            || ($auditLog->employee_id !== null && in_array((int) $auditLog->employee_id, $employeeIds, true)),
            403,
            'auth.forbidden'
        );
    }
}
