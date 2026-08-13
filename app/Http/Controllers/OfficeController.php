<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => OfficeResource::collection(Office::orderBy('name')->get())]);
    }

    public function store(Request $request): JsonResponse
    {
        $office = Office::create($this->validated($request));
        return response()->json(['success' => true, 'data' => new OfficeResource($office)], 201);
    }

    public function show(Office $office): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new OfficeResource($office)]);
    }

    public function update(Request $request, Office $office): JsonResponse
    {
        $oldName = $office->name;
        $office->update($this->validated($request, $office));

        if ($oldName !== $office->name)
            Stock::where('office', $oldName)->update(['office' => $office->name]);

        return response()->json(['success' => true, 'data' => new OfficeResource($office->fresh())]);
    }

    public function destroy(Office $office): JsonResponse
    {
        if (Stock::where('office', $office->name)->exists()) {
            return response()->json(['success' => false, 'message' => 'This office has stock assigned to it and cannot be deleted.'], 409);
        }

        $office->delete();
        return response()->json(['success' => true, 'message' => 'Office deleted.']);
    }

    private function validated(Request $request, ?Office $office = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('offices', 'name')->whereNull('deleted_at')->ignore($office?->id)],
            'code' => ['required', 'string', 'max:30', Rule::unique('offices', 'code')->whereNull('deleted_at')->ignore($office?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_if:geofence_enabled,true'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_if:geofence_enabled,true'],
            'geofence_radius_meters' => ['sometimes', 'integer', 'between:10,5000'],
            'geofence_enabled' => ['sometimes', 'boolean'],
        ]);
    }
}
