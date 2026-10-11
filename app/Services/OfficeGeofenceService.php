<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Office;
use App\Support\CompanyProfile;

class OfficeGeofenceService
{
    private const MAX_ACCURACY_METERS = 100;

    public function __construct(
        private readonly CompanyProfile $company,
    ) {}

    public function configuration(?Employee $employee): array
    {
        if (! $this->company->automaticAttendanceEnabled()) {
            return ['eligible' => false, 'reason' => 'automatic_attendance_disabled', 'timezone' => $this->company->timezone(), 'office' => null];
        }

        $employee?->loadMissing('office');
        $office = $employee?->office;

        return [
            'eligible' => (bool) ($employee && $employee->status === Employee::STATUS_ACTIVE && $office?->hasActiveGeofence()),
            'reason' => ! $employee ? 'no_employee'
                : ($employee->status !== Employee::STATUS_ACTIVE ? 'inactive_employee' : (! $office ? 'no_office'
                    : (! $office->hasActiveGeofence() ? 'geofence_not_configured' : null))),
            'timezone' => $this->company->timezone(),
            'office' => $office ? $this->officePayload($office) : null,
        ];
    }

    public function observe(Employee $employee, float $latitude, float $longitude, int $accuracyMeters): array
    {
        abort_if(! $this->company->automaticAttendanceEnabled(), 422, 'business.automatic_attendance_disabled');

        abort_unless($employee->status === Employee::STATUS_ACTIVE, 422, 'business.attendance_employee_inactive');
        $employee->loadMissing('office');
        $office = $employee->office;
        abort_unless($office?->hasActiveGeofence(), 422, 'business.office_geofence_unavailable');

        $distance = $this->distanceMeters(
            (float) $office->latitude,
            (float) $office->longitude,
            $latitude,
            $longitude,
        );

        if ($accuracyMeters > self::MAX_ACCURACY_METERS) {
            return $this->result('inaccurate', $office, $distance, $accuracyMeters);
        }

        $radius = (int) $office->geofence_radius_meters;
        // Only classify as inside when the whole uncertainty circle fits.
        $action = $distance + $accuracyMeters <= $radius ? 'inside'
            : ($distance - max(15, $accuracyMeters) > $radius ? 'outside' : 'boundary');

        return $this->result($action, $office, $distance, $accuracyMeters);
    }

    private function result(string $action, Office $office, int $distance, int $accuracy): array
    {
        // Location checks are evidence only. Explicit Start/End actions own punches.
        return [
            'action' => $action,
            'inside' => $action === 'inside',
            'distance_meters' => $distance,
            'accuracy_meters' => $accuracy,
            'exit_confirmation_seconds_remaining' => 0,
            'office' => $this->officePayload($office),
            'attendance' => null,
        ];
    }

    private function officePayload(Office $office): array
    {
        return [
            'id' => $office->id, 'name' => $office->name,
            'latitude' => $office->latitude !== null ? (float) $office->latitude : null,
            'longitude' => $office->longitude !== null ? (float) $office->longitude : null,
            'radius_meters' => (int) $office->geofence_radius_meters,
            'enabled' => $office->hasActiveGeofence(),
        ];
    }

    public function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): int
    {
        $earthRadius = 6371008.8;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;
        $a = min(1.0, max(0.0, $a));

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
