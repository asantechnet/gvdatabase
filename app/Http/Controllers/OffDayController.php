<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\OffDayResource;
use App\Models\OffDay;
use App\Services\OffDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR off-day ledger.
 *
 * Announcing and approving are separate capabilities: `manage_off_days` lets
 * HR record a range, `approve_off_days` activates it. A manager who holds both
 * announces an already-approved holiday in one step.
 */
class OffDayController extends Controller
{
    use PaginatesLists;
    public function __construct(private readonly OffDayService $offDays) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,3000'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in([OffDay::STATUS_PENDING, OffDay::STATUS_APPROVED, OffDay::STATUS_REJECTED])],
        ]);

        $query = OffDay::query()->with(['approver:id,name', 'creator:id,name']);
        if (! empty($data['year'])) {
            $query->where(function ($nested) use ($data): void {
                $nested->whereYear('start_date', $data['year'])
                    ->orWhereYear('end_date', $data['year']);
            });
        }
        if (! empty($data['from']) && ! empty($data['to'])) {
            $query->overlapping($data['from'], $data['to']);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $query->orderByDesc('start_date')->orderByDesc('id');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true]
                + $this->paginatedPayload($request, $query, fn ($rows) => OffDayResource::collection($rows)->resolve()));
        }

        return response()->json([
            'success' => true,
            'data' => OffDayResource::collection($query->get())->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $canApprove = $request->user()->hasPermission('approve_off_days');

        $offDay = OffDay::create([
            ...$data,
            'status' => $canApprove ? OffDay::STATUS_APPROVED : OffDay::STATUS_PENDING,
            'created_by' => $request->user()->id,
            'approved_by' => $canApprove ? $request->user()->id : null,
            'approved_at' => $canApprove ? now() : null,
        ]);

        if ($offDay->isApproved()) {
            $this->offDays->apply($offDay);
        }

        return response()->json([
            'success' => true,
            'message' => $canApprove ? 'Off day announced.' : 'Off day submitted for approval.',
            'data' => new OffDayResource($offDay->load(['approver:id,name', 'creator:id,name'])),
        ], 201);
    }

    public function update(Request $request, OffDay $offDay): JsonResponse
    {
        $canApprove = $request->user()->hasPermission('approve_off_days');
        if ($offDay->isApproved() && ! $canApprove) {
            throw ValidationException::withMessages(['status' => ['business.off_day_already_approved']]);
        }

        $data = $this->validated($request);
        $wasApproved = $offDay->isApproved();

        if ($wasApproved) {
            $this->offDays->revert($offDay);
        }

        $offDay->update([
            ...$data,
            'status' => OffDay::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        if ($wasApproved) {
            $offDay->forceFill([
                'status' => OffDay::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();
            $this->offDays->apply($offDay);
        }

        return response()->json([
            'success' => true,
            'data' => new OffDayResource($offDay->fresh(['approver:id,name', 'creator:id,name'])),
        ]);
    }

    public function approve(Request $request, OffDay $offDay): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve_off_days'), 403, 'auth.forbidden');
        if ($offDay->isApproved()) {
            return response()->json([
                'success' => true,
                'data' => new OffDayResource($offDay->load(['approver:id,name', 'creator:id,name'])),
            ]);
        }

        $offDay->update([
            'status' => OffDay::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $this->offDays->apply($offDay);

        return response()->json([
            'success' => true,
            'message' => 'Off day approved.',
            'data' => new OffDayResource($offDay->fresh(['approver:id,name', 'creator:id,name'])),
        ]);
    }

    public function reject(Request $request, OffDay $offDay): JsonResponse
    {
        abort_unless($request->user()->hasPermission('approve_off_days'), 403, 'auth.forbidden');
        if ($offDay->isApproved()) {
            $this->offDays->revert($offDay);
        }

        $offDay->update([
            'status' => OffDay::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Off day rejected.',
            'data' => new OffDayResource($offDay->fresh(['approver:id,name', 'creator:id,name'])),
        ]);
    }

    public function destroy(Request $request, OffDay $offDay): JsonResponse
    {
        if ($offDay->isApproved()) {
            abort_unless($request->user()->hasPermission('approve_off_days'), 403, 'auth.forbidden');
            $this->offDays->revert($offDay);
        }

        $offDay->delete();

        return response()->json(['success' => true, 'message' => 'Off day removed.']);
    }

    /** @return array{start_date: string, end_date: string, reason: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages([
                'end_date' => ['business.off_day_range_too_long'],
            ]);
        }

        return $data;
    }
}
