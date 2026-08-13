<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->visibleTo($request)
            ->when($request->filled('is_read'), fn (Builder $query) => $query->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereKey($request->user()->id)
                ->where('notification_user.is_read', $request->boolean('is_read'))
                ->whereNull('notification_user.deleted_at')))
            ->orderByDesc('date_time')
            ->limit(min(max($request->integer('limit', 50), 1), 100))
            ->get();

        return response()->json(['success' => true, 'data' => NotificationResource::collection($notifications)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $data = $this->validated($request);
        $recipientIds = $this->recipientIds($data);
        unset($data['user_ids']);

        $notification = Notification::create($data);
        $notification->deliverTo($recipientIds);

        return response()->json(['success' => true, 'data' => new NotificationResource($this->forUser($notification, $request->user()->id))], 201);
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $notification = $this->visibleTo($request)->findOrFail($notification->id);

        return response()->json(['success' => true, 'data' => new NotificationResource($notification)]);
    }

    public function update(Request $request, Notification $notification): JsonResponse
    {
        if ($request->has('is_read')) {
            $data = $request->validate(['is_read' => ['nullable', 'boolean']]);
            $notification = $this->visibleTo($request)->findOrFail($notification->id);
            $isRead = $data['is_read'];

            NotificationUser::query()
                ->where('notification_id', $notification->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('deleted_at')
                ->update([
                    'is_read' => $isRead,
                    'read_at' => $isRead === true ? now() : null,
                    'updated_at' => now(),
                ]);

            return response()->json(['success' => true, 'data' => new NotificationResource($this->forUser($notification, $request->user()->id))]);
        }

        $this->authorizeManagement($request);
        $data = $this->validated($request, true);
        unset($data['user_ids']);
        $notification->update($data);

        return response()->json(['success' => true, 'data' => new NotificationResource($this->forUser($notification->fresh(), $request->user()->id))]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        NotificationUser::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->where(fn (Builder $query) => $query->whereNull('is_read')->orWhere('is_read', false))
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->visibleTo($request)->findOrFail($notification->id);

        NotificationUser::query()
            ->where('notification_id', $notification->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Notification removed from your inbox.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : 'required|';
        $validator = Validator::make($request->all(), [
            'type' => $prefix.'string|max:50',
            'notification_key' => $prefix.'string|max:100',
            'data' => 'nullable|array',
            'date_time' => 'nullable|date',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'user_ids' => 'sometimes|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'go_to_url' => ['nullable', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
        ]);
        $data = $validator->validate();

        if (! $partial) {
            $data['date_time'] = $data['date_time'] ?? now();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function recipientIds(array $data): array
    {
        if (array_key_exists('user_ids', $data)) {
            return User::query()->where('acc_status', 'active')->whereIn('id', $data['user_ids'])->pluck('id')->all();
        }

        if (! empty($data['employee_id'])) {
            $userId = Employee::query()->whereKey($data['employee_id'])->value('user_id');

            if (! $userId) {
                throw ValidationException::withMessages(['employee_id' => ['business.employee_has_no_account']]);
            }

            return [$userId];
        }

        return User::query()->where('acc_status', 'active')->pluck('id')->all();
    }

    private function visibleTo(Request $request): Builder
    {
        $userId = $request->user()->id;

        return Notification::query()
            ->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereKey($userId)
                ->whereNull('notification_user.deleted_at'))
            ->with(['recipients' => fn (BelongsToMany $recipients) => $recipients
                ->whereKey($userId)
                ->whereNull('notification_user.deleted_at')]);
    }

    private function forUser(Notification $notification, int $userId): Notification
    {
        return $notification->load(['recipients' => fn (BelongsToMany $recipients) => $recipients
            ->whereKey($userId)
            ->whereNull('notification_user.deleted_at')]);
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($this->canManage($request), 403, 'auth.forbidden');
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->hasPermission('send_notifications');
    }
}
