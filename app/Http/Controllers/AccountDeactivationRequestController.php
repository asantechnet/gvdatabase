<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountDeactivationRequestResource;
use App\Http\Resources\UserResource;
use App\Models\AccountDeactivationRequest;
use App\Models\User;
use App\Services\AccountDeactivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountDeactivationRequestController extends Controller
{
    public function __construct(private readonly AccountDeactivationService $service) {}

    public function mine(Request $request): JsonResponse
    {
        $items = AccountDeactivationRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['user', 'employee', 'reviewer'])
            ->latest('requested_at')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => AccountDeactivationRequestResource::collection($items)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $deactivation = $this->service->request(
            $request->user(),
            $data['current_password'],
            $data['reason'] ?? null,
            $request,
        );

        return response()->json(['success' => true, 'data' => new AccountDeactivationRequestResource($deactivation)], 201);
    }

    public function cancel(Request $request, AccountDeactivationRequest $accountDeactivationRequest): JsonResponse
    {
        $deactivation = $this->service->cancel($accountDeactivationRequest, $request->user(), $request);

        return response()->json(['success' => true, 'data' => new AccountDeactivationRequestResource($deactivation)]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AccountDeactivationRequestResource::collection($this->service->visibleToReviewer($request->user())),
        ]);
    }

    public function review(Request $request, AccountDeactivationRequest $accountDeactivationRequest): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'decision_notes' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'max:2000'],
        ]);
        $deactivation = $this->service->review(
            $accountDeactivationRequest,
            $request->user(),
            $data['decision'],
            $data['decision_notes'] ?? null,
            $request,
        );

        return response()->json(['success' => true, 'data' => new AccountDeactivationRequestResource($deactivation)]);
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['decision_notes' => ['nullable', 'string', 'max:2000']]);
        $user = $this->service->reactivate($user, $request->user(), $data['decision_notes'] ?? null, $request);

        return response()->json(['success' => true, 'data' => new UserResource($user)]);
    }
}
