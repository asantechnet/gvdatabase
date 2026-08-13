<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->list($request, 'view_audit_logs');
    }

    public function deleted(Request $request): JsonResponse
    {
        $request->merge(['event' => 'deleted']);

        return $this->list($request, 'view_deleted_records');
    }

    private function list(Request $request, string $permission): JsonResponse
    {
        $this->authorizePermission($request, $permission);
        $data = $request->validate([
            'table' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'in:created,updated,deleted,restored'],
            'review_status' => ['nullable', 'in:pending,approved,discarded'],
            'row_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()->with(['user:id,name,email', 'employee:id,name', 'reviewer:id,name,email'])
            ->when(isset($data['table']), fn ($query) => $query->where('table_name', $data['table']))
            ->when(isset($data['event']), fn ($query) => $query->where('event', $data['event']))
            ->when(isset($data['review_status']), fn ($query) => $query->where('review_status', $data['review_status']))
            ->when(isset($data['row_id']), fn ($query) => $query->where('auditable_id', $data['row_id']))
            ->latest()->paginate($data['per_page'] ?? 50);

        return response()->json($logs);
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizePermission($request, 'view_audit_logs');

        return response()->json([
            'success' => true,
            'data' => $auditLog->load(['user:id,name,email', 'employee:id,name', 'reviewer:id,name,email']),
        ]);
    }

    public function revert(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizePermission($request, 'revert_audit_changes');
        $model = $this->discard($auditLog, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Change reverted. The revert has been added to the audit trail.',
            'data' => $model->fresh(),
        ]);
    }

    public function review(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizePermission($request, 'review_audit_logs');
        $data = $request->validate(['decision' => ['required', 'in:approved,discarded']]);
        abort_unless($auditLog->review_status === 'pending', 409, 'business.audit_already_reviewed');

        if ($data['decision'] === 'discarded') {
            $this->discard($auditLog);
        } else {
            $auditLog->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Audit change '.$data['decision'].' and removed.',
            'audit_log_id' => $auditLog->id,
        ]);
    }

    private function discard(AuditLog $auditLog): Model
    {
        return DB::transaction(function () use ($auditLog) {
            $model = $this->auditedModel($auditLog);
            abort_if($auditLog->review_status !== 'pending', 409, 'business.audit_already_reviewed');
            abort_if(
                AuditLog::query()
                    ->where('auditable_type', $auditLog->auditable_type)
                    ->where('auditable_id', $auditLog->auditable_id)
                    ->where('id', '>', $auditLog->id)
                    ->exists(),
                409,
                'business.audit_not_latest_change'
            );

            if ($auditLog->event === 'deleted') {
                abort_unless($model->trashed(), 409, 'business.audit_row_not_deleted');
                $model->restore();
            } elseif ($auditLog->event === 'updated') {
                abort_if($model->trashed(), 409, 'business.audit_row_deleted');
                $model->forceFill($auditLog->old_values ?? [])->save();
            } else {
                abort(422, 'business.audit_event_not_revertible');
            }

            $auditLog->delete();

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

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, 'auth.forbidden');
    }
}
