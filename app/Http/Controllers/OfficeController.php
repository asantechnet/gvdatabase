<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => OfficeResource::collection(Office::withCount('assets')->orderBy('name')->get())]);
    }

    public function store(Request $request): JsonResponse
    {
        // The unique `is_singleton` marker is the final concurrency guard;
        // this preflight gives ordinary API clients a useful business code.
        if (Office::withTrashed()->exists()) {
            return response()->json(['success' => false, 'code' => 'business.single_office_only'], 409);
        }

        $office = Office::create($this->validated($request));

        return response()->json(['success' => true, 'data' => new OfficeResource($office->loadCount('assets'))], 201);
    }

    public function show(Office $office): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new OfficeResource($office->loadCount('assets'))]);
    }

    public function update(Request $request, Office $office): JsonResponse
    {
        $office->update($this->validated($request, $office));

        return response()->json(['success' => true, 'data' => new OfficeResource($office->loadCount('assets'))]);
    }

    public function destroy(Office $office): JsonResponse
    {
        return response()->json(['success' => false, 'code' => 'business.office_cannot_be_deleted'], 409);
    }

    private function validated(Request $request, ?Office $office = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('offices', 'name')->whereNull('deleted_at')->ignore($office?->id)],
            'code' => ['required', 'string', 'max:30', Rule::unique('offices', 'code')->whereNull('deleted_at')->ignore($office?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['sometimes', 'integer', 'between:10,5000'],
            'geofence_enabled' => ['sometimes', 'boolean'],
        ], ['required' => __('settings.validation.required')], (array) __('settings.fields'));
        $enabled = $validated['geofence_enabled'] ?? $office?->geofence_enabled ?? false;
        if ($enabled) {
            $errors = [];
            foreach (['latitude', 'longitude'] as $coordinate) {
                $value = array_key_exists($coordinate, $validated) ? $validated[$coordinate] : $office?->{$coordinate};
                if ($value === null) {
                    $errors[$coordinate] = [__('settings.validation.required', ['attribute' => __('settings.fields.'.$coordinate)])];
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return $validated;
    }
}
