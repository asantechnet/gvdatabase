<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\NotificationResource;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationUser;
use App\Models\User;
use App\Support\EmployeeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    use PaginatesLists;

    public function index(Request $request): JsonResponse
    {
        $visible = $this->visibleTo($request);
        $unreadCount = (clone $visible)
            ->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereKey($request->user()->id)
                ->whereNull('notification_user.deleted_at')
                ->where(fn (Builder $state) => $state
                    ->whereNull('notification_user.is_read')
                    ->orWhere('notification_user.is_read', false)))
            ->count();
        $query = $visible
            ->when($request->has('is_read'), fn (Builder $query) => $query->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereKey($request->user()->id)
                ->where('notification_user.is_read', $request->boolean('is_read'))
                ->whereNull('notification_user.deleted_at')))
            ->orderByDesc('date_time');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true, 'unread_count' => $unreadCount]
                + $this->paginatedPayload($request, $query, fn ($rows) => NotificationResource::collection($rows)->resolve()));
        }

        $notifications = $query
            ->limit(min(max($request->integer('limit', 50), 1), 100))
            ->get();

        return response()->json(['success' => true, 'data' => NotificationResource::collection($notifications)]);
    }

    /**
     * Real-time delivery channel. Returns the user's unread notifications only
     * while their `unread_notifications` flag is raised, then clears the flag so
     * a batch is delivered exactly once. Cheap for the common (nothing new) case:
     * the flag is checked before any notification content is queried.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->unread_notifications) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $notifications = DB::transaction(function () use ($request, $user) {
            // Serialize concurrent polls on the user row so a batch cannot be
            // returned twice when two heartbeat requests overlap.
            $pending = (bool) User::query()->whereKey($user->getKey())->lockForUpdate()->value('unread_notifications');
            if (! $pending) {
                return collect();
            }

            $rows = $this->visibleTo($request, true)
                ->orderByDesc('date_time')
                ->limit(20)
                ->get();

            User::query()->whereKey($user->getKey())->update(['unread_notifications' => false]);
            $user->unread_notifications = false;

            return $rows;
        });

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
        $actor = request()->user();
        if (array_key_exists('user_ids', $data)) {
            $recipientIds = User::query()->where('acc_status', 'active')->whereIn('id', $data['user_ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        } elseif (! empty($data['employee_id'])) {
            $userId = Employee::query()->whereKey($data['employee_id'])->value('user_id');

            if (! $userId) {
                throw ValidationException::withMessages(['employee_id' => ['business.employee_has_no_account']]);
            }

            $recipientIds = [(int) $userId];
        } else {
            abort_unless($actor->hasPermission('send_all_notifications'), 403, 'auth.forbidden');

            return User::query()->where('acc_status', 'active')->pluck('id')->all();
        }

        if (! $actor->hasPermission('send_all_notifications')) {
            $allowed = app(EmployeeScope::class)->visibleUserIds($actor);
            abort_unless(collect($recipientIds)->every(fn (int $id) => in_array($id, $allowed, true)), 403, 'auth.forbidden');
        }

        return $recipientIds;
    }

    private function visibleTo(Request $request, bool $onlyUnread = false): Builder
    {
        $userId = $request->user()->id;

        return Notification::query()
            ->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->whereKey($userId)
                ->whereNull('notification_user.deleted_at')
                ->when($onlyUnread, fn (Builder $unread) => $unread->where(fn (Builder $state) => $state
                    ->whereNull('notification_user.is_read')
                    ->orWhere('notification_user.is_read', false))))
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
