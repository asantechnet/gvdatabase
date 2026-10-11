<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuditResourceSetting;
use App\Models\Employee;
use App\Support\AuditRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'view_audit_logs');
        $data = $request->validate([
            'state' => ['nullable', 'in:enabled,disabled'],
            'search' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        AuditRegistry::ensureSettings();

        $paginator = AuditResourceSetting::query()->whereIn('resource_key', array_keys(AuditRegistry::resources()))->with('updatedBy:id,name,email')
            ->when(($data['state'] ?? null) === 'enabled', fn ($query) => $query->where('enabled', true))
            ->when(($data['state'] ?? null) === 'disabled', fn ($query) => $query->where('enabled', false))
            ->when(isset($data['search']), fn ($query) => $query->where('resource_key', 'like', '%'.addcslashes($data['search'], '%_\\').'%'))
            ->orderBy('resource_key')
            ->paginate($data['per_page'] ?? 25);
        $paginator->setCollection($paginator->getCollection()->map(fn (AuditResourceSetting $setting) => [
            'id' => $setting->id,
            'resource_key' => $setting->resource_key,
            'enabled' => $setting->enabled,
            'updated_by' => $setting->updatedBy ? ['id' => $setting->updatedBy->id, 'name' => $setting->updatedBy->name] : null,
            'updated_at' => $setting->updated_at?->toISOString(),
        ]));

        return response()->json($paginator->toArray() + [
            'resource_keys' => [...array_keys(AuditRegistry::resources()), 'account_deactivations', 'audit_settings'],
        ]);
    }

    public function update(Request $request, AuditResourceSetting $auditResourceSetting): JsonResponse
    {
        $this->authorizePermission($request, 'manage_audit_scope');
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $oldEnabled = $auditResourceSetting->enabled;

        DB::transaction(function () use ($request, $auditResourceSetting, $data, $oldEnabled) {
            $auditResourceSetting->update([
                'enabled' => $data['enabled'],
                'updated_by' => $request->user()->id,
            ]);

            if ($oldEnabled !== $data['enabled']) {
                AuditLog::create([
                    'event' => $data['enabled'] ? 'audit_scope_enabled' : 'audit_scope_disabled',
                    'table_name' => 'audit_resource_settings',
                    'auditable_type' => AuditResourceSetting::class,
                    'auditable_id' => $auditResourceSetting->id,
                    'subject_type' => 'audit_settings',
                    'subject_label' => $auditResourceSetting->resource_key,
                    'user_id' => $request->user()->id,
                    'employee_id' => Employee::withTrashed()->where('user_id', $request->user()->id)->value('id'),
                    'old_values' => ['enabled' => $oldEnabled],
                    'new_values' => ['enabled' => $data['enabled']],
                    'changed_columns' => ['enabled'],
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip_address' => $request->ip(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $auditResourceSetting->id,
                'resource_key' => $auditResourceSetting->resource_key,
                'enabled' => $auditResourceSetting->enabled,
                'updated_by' => ['id' => $request->user()->id, 'name' => $request->user()->name],
                'updated_at' => $auditResourceSetting->updated_at?->toISOString(),
            ],
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, 'auth.forbidden');
    }
}
