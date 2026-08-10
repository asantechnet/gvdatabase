<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->visibleTo($request)
            ->when($request->filled('is_read'), fn ($query) => $query->where('is_read', $request->boolean('is_read')))
            ->orderByDesc('date_time')
            ->limit(min(max($request->integer('limit', 50), 1), 100))
            ->get();

        return response()->json(['success' => true, 'data' => NotificationResource::collection($notifications)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $notification = Notification::create($this->validated($request));
        return response()->json(['success' => true, 'data' => new NotificationResource($notification)], 201);
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $this->ensureVisible($request, $notification);
        return response()->json(['success' => true, 'data' => new NotificationResource($notification)]);
    }

    public function update(Request $request, Notification $notification): JsonResponse
    {
        if ($request->has('is_read') && !$this->canManage($request) && $this->visibleTo($request)->whereKey($notification->id)->doesntExist()) {
            abort(404);
        }
        if (!$request->has('is_read')) $this->authorizeManagement($request);

        $data = $request->has('is_read') ? $request->validate(['is_read' => 'nullable|boolean']) : $this->validated($request, true);
        $notification->update($data);
        return response()->json(['success' => true, 'data' => new NotificationResource($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->visibleTo($request)->where(fn ($query) => $query->whereNull('is_read')->orWhere('is_read', false))->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeManagement($request);
        $notification->delete();
        return response()->json(['success' => true, 'message' => 'Notification deleted successfully.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : 'required|';
        $validator = Validator::make($request->all(), [
            'type' => $prefix.'string|max:50',
            'title' => $prefix.'string|max:255',
            'message' => $prefix.'string|max:5000',
            'date_time' => 'nullable|date',
            'is_read' => 'nullable|boolean',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'go_to_url' => ['nullable', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
        ]);
        $validator->validate();
        $data = $validator->validated();
        if (!$partial) {
            $data['date_time'] = $data['date_time'] ?? now();
            $data['is_read'] = $data['is_read'] ?? null;
        }
        return $data;
    }

    private function visibleTo(Request $request)
    {
        $employeeId = Employee::where('user_id', $request->user()->id)->value('id');
        return Notification::query()->where(function ($query) use ($employeeId) {
            $query->whereNull('employee_id');
            if ($employeeId) $query->orWhere('employee_id', $employeeId);
        });
    }

    private function ensureVisible(Request $request, Notification $notification): void
    {
        if (!$this->canManage($request) && $this->visibleTo($request)->whereKey($notification->id)->doesntExist()) abort(404);
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($this->canManage($request), 403, 'You are not allowed to manage notifications.');
    }

    private function canManage(Request $request): bool
    {
        $roles = is_array($request->user()->roles) ? $request->user()->roles : json_decode($request->user()->roles ?? '[]', true);
        return (bool) array_intersect((array) $roles, ['admin', 'super_admin']);
    }
}
