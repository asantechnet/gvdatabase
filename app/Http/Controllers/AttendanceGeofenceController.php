<?php

namespace App\Http\Controllers;

use App\Services\OfficeGeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceGeofenceController extends Controller
{
    public function show(Request $request, OfficeGeofenceService $geofence): JsonResponse
    {
        return response()->json(['data' => $geofence->configuration($request->user()->employee)]);
    }

    public function observe(Request $request, OfficeGeofenceService $geofence): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'between:0,5000'],
        ]);
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');

        return response()->json(['data' => $geofence->observe(
            $employee,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (int) ceil((float) $validated['accuracy_meters']),
        )]);
    }
}
