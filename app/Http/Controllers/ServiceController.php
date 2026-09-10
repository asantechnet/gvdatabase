<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $services = Service::with('currency')->get();

        return response()->json(ServiceResource::collection($services));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'duration' => 'required|integer|min:1',
            'fee' => 'required|numeric|min:0',
            'status' => [
                'required',
                'string',
                Rule::in(Service::getValidStatuses()),
            ],
            'currency_id' => 'required|exists:currencies,id',
        ]);

        $service = Service::create($validated);

        return response()->json(new ServiceResource($service->load('currency')), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json(new ServiceResource($service->load('currency')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|required|string|max:255',
            'duration' => 'sometimes|required|integer|min:1',
            'fee' => 'sometimes|required|numeric|min:0',
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Service::getValidStatuses()),
            ],
            'currency_id' => 'sometimes|required|exists:currencies,id',
        ]);

        $service->update($validated);

        return response()->json(new ServiceResource($service->load('currency')));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service): JsonResponse
    {
        if ($service->invoices()->exists() || $service->customerServices()->exists() || $service->projects()->exists()) {
            return response()->json([
                'message' => 'Assigned, projected, or invoiced services cannot be deleted. Mark the service inactive instead.',
            ], 409);
        }

        $service->delete();

        return response()->json(null, 204);
    }
}
