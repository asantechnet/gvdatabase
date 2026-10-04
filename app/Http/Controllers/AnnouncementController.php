<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Announcements are immutable: the API intentionally exposes no update or
 * delete route, for anyone. See asantech-mini-management/ANNOUNCEMENTS_FEATURE_PLAN.md.
 */
class AnnouncementController extends Controller
{
    private const SCOPE_TYPES = ['all_employees', 'position', 'role', 'department', 'applicants'];

    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()->with('sender:id,name');

        if (! $this->canViewAll($request)) {
            $query->where('sender_id', $request->user()->id);
        }

        $announcements = $query->latest()->get();

        return response()->json(['success' => true, 'data' => AnnouncementResource::collection($announcements)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('send_announcements'), 403, 'auth.forbidden');

        $data = $this->validated($request);
        $audience = $data['audience'];
        $this->authorizeAudience($user, $audience);

        $announcement = Announcement::create([
            'sender_id' => $user->id,
            'title' => $data['title'] ?? null,
            'message' => $data['message'],
            'labels' => $data['labels'] ?? [],
            'audience' => $audience,
        ]);

        $delivered = $this->announcements->announce($announcement, $audience);

        return response()->json([
            'success' => true,
            'data' => new AnnouncementResource($announcement->load('sender:id,name')),
            'meta' => ['delivered_to' => $delivered],
        ], 201);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless(
            $announcement->sender_id === $request->user()->id || $this->canViewAll($request),
            403,
            'auth.forbidden',
        );

        return response()->json(['success' => true, 'data' => new AnnouncementResource($announcement->load('sender:id,name'))]);
    }

    /**
     * Recipient preview for the composer: how many active accounts currently
     * match each selected scope, plus the selectable position/role/department
     * options.
     */
    public function audienceOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('send_announcements'), 403, 'auth.forbidden');

        $types = array_values(array_intersect(
            self::SCOPE_TYPES,
            (array) $request->query('types', self::SCOPE_TYPES),
        ));

        $preview = [];

        foreach ($types as $type) {
            $preview[$type] = $this->announcements->recipientIdsFor([['type' => $type]])->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'counts' => $preview,
                'positions' => Position::query()->orderBy('title')->get(['id', 'title']),
                'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
                'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /** @param array<int, array{type: string}> $audience */
    private function authorizeAudience(object $user, array $audience): void
    {
        foreach ($audience as $scope) {
            if ($scope['type'] === 'all_employees') {
                abort_unless(
                    $user->hasPermission('announce_to_all_employees') && $user->hasPermission('send_all_notifications'),
                    403,
                    'auth.forbidden',
                );
            }

            if ($scope['type'] === 'applicants') {
                abort_unless($user->hasPermission('announce_to_applicants'), 403, 'auth.forbidden');
            }
        }
    }

    private function canViewAll(Request $request): bool
    {
        return $request->user()->hasPermission('view_all_announcements');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:65535'],
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['string', 'max:50'],
            'audience' => ['required', 'array', 'min:1'],
            'audience.*.type' => ['required', 'string', 'in:'.implode(',', self::SCOPE_TYPES)],
            'audience.*.id' => ['required_if:audience.*.type,position,role,department', 'nullable', 'integer', 'min:1'],
        ]);
        $data = $validator->validate();

        foreach ($data['audience'] as $index => $scope) {
            if (in_array($scope['type'], ['position', 'role', 'department'], true)
                && ! $this->scopeTargetExists($scope['type'], (int) ($scope['id'] ?? 0))) {
                throw ValidationException::withMessages([
                    "audience.{$index}.id" => ['validation.exists'],
                ]);
            }
        }

        $data['labels'] = collect($data['labels'] ?? [])
            ->map(fn (string $label) => trim($label))
            ->filter(fn (string $label) => $label !== '')
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    private function scopeTargetExists(string $type, int $id): bool
    {
        return match ($type) {
            'position' => Position::query()->whereKey($id)->exists(),
            'role' => Role::query()->whereKey($id)->exists(),
            'department' => Department::query()->whereKey($id)->exists(),
            default => true,
        };
    }
}
