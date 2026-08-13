<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Office;
use App\Support\CompanyProfile;
use Illuminate\Support\Facades\DB;

class OfficeGeofenceService
{
    private const MAX_ACCURACY_METERS = 100;
    private const EXIT_CONFIRMATION_SECONDS = 120;

    public function __construct(
        private readonly CompanyProfile $company,
        private readonly AttendanceCalculationService $calculator,
    ) {}

    public function configuration(?Employee $employee): array
    {
        $employee?->loadMissing('office');
        $office = $employee?->office;

        return [
            'eligible' => (bool) ($employee && $office?->hasActiveGeofence()),
            'reason' => ! $employee ? 'no_employee'
                : (! $office ? 'no_office'
                    : (! $office->hasActiveGeofence() ? 'geofence_not_configured' : null)),
            'timezone' => $this->company->timezone(),
            'office' => $office ? $this->officePayload($office) : null,
        ];
    }

    public function observe(Employee $employee, float $latitude, float $longitude, int $accuracyMeters): array
    {
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
        if ($distance <= $radius) {
            return $this->inside($employee, $office, $latitude, $longitude, $accuracyMeters, $distance);
        }

        // Treat the GPS uncertainty band around the configured circle as an
        // indeterminate boundary instead of clocking somebody out on jitter.
        if ($distance <= $radius + max(15, $accuracyMeters)) {
            return $this->result('boundary', $office, $distance, $accuracyMeters);
        }

        return $this->outside($employee, $office, $latitude, $longitude, $accuracyMeters, $distance);
    }

    private function inside(Employee $employee, Office $office, float $latitude, float $longitude, int $accuracy, int $distance): array
    {
        $localNow = now($this->company->timezone());
        $utcNow = now('UTC');

        [$attendance, $checkedInNow] = DB::transaction(function () use ($employee, $office, $latitude, $longitude, $accuracy, $distance, $localNow, $utcNow) {
            $attendance = Attendance::withTrashed()
                ->where('employee_id', $employee->id)
                ->where('date', $localNow->toDateString())
                ->lockForUpdate()
                ->first();

            if ($attendance && ! $attendance->trashed() && $attendance->status !== 'present') {
                return [$attendance, false];
            }

            if ($attendance?->checked_out_at || $attendance?->check_out_time) {
                return [$attendance, false];
            }

            $values = [
                'status' => 'present',
                'logged_at' => $localNow->format('H:i:s'),
                'check_in_time' => $attendance?->check_in_time ?: $localNow->format('H:i:s'),
                'checked_in_at' => $attendance?->checked_in_at ?: $utcNow,
                'office_id' => $office->id,
                'geofence_exit_detected_at' => null,
            ];

            if (! $attendance?->checked_in_at && ! $attendance?->check_in_time) {
                $values += [
                    'check_in_latitude' => $latitude,
                    'check_in_longitude' => $longitude,
                    'check_in_accuracy_meters' => $accuracy,
                    'check_in_distance_meters' => $distance,
                    'check_in_source' => 'office_geofence',
                ];
            }

            if ($attendance) {
                $checkedInNow = ! $attendance->checked_in_at && ! $attendance->check_in_time;
                $attendance->fill($values);
                if ($attendance->trashed()) $attendance->restore();
                $attendance->save();

                return [$attendance, $checkedInNow];
            }

            return [Attendance::create(['employee_id' => $employee->id, 'date' => $localNow->toDateString(), ...$values,
                'check_in_latitude' => $latitude, 'check_in_longitude' => $longitude,
                'check_in_accuracy_meters' => $accuracy, 'check_in_distance_meters' => $distance,
                'check_in_source' => 'office_geofence',
            ]), true];
        });

        if ($attendance->status !== 'present') return $this->result('blocked', $office, $distance, $accuracy, $attendance);
        if ($attendance->checked_out_at || $attendance->check_out_time) return $this->result('already_completed', $office, $distance, $accuracy, $attendance);

        $this->calculator->calculate($attendance, true);

        return $this->result($checkedInNow ? 'checked_in' : 'inside', $office, $distance, $accuracy, $attendance);
    }

    private function outside(Employee $employee, Office $office, float $latitude, float $longitude, int $accuracy, int $distance): array
    {
        $utcNow = now('UTC');
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'present')
            ->whereNull('checked_out_at')
            ->whereNull('check_out_time')
            ->latest('date')
            ->first();

        if (! $attendance) return $this->result('outside', $office, $distance, $accuracy);

        $action = DB::transaction(function () use ($attendance, $office, $latitude, $longitude, $accuracy, $distance, $utcNow) {
            $locked = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);
            if ($locked->checked_out_at || $locked->check_out_time) return ['already_completed', $locked, 0];

            if (! $locked->geofence_exit_detected_at) {
                $locked->update(['geofence_exit_detected_at' => $utcNow]);
                return ['exit_pending', $locked, self::EXIT_CONFIRMATION_SECONDS];
            }

            $elapsed = $locked->geofence_exit_detected_at->diffInSeconds($utcNow);
            if ($elapsed < self::EXIT_CONFIRMATION_SECONDS) {
                return ['exit_pending', $locked, self::EXIT_CONFIRMATION_SECONDS - $elapsed];
            }

            $localNow = $utcNow->copy()->timezone($this->company->timezone());
            $locked->update([
                'check_out_time' => $localNow->format('H:i:s'),
                'checked_out_at' => $utcNow,
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'check_out_accuracy_meters' => $accuracy,
                'check_out_distance_meters' => $distance,
                'check_out_source' => 'office_geofence',
                'geofence_exit_detected_at' => null,
            ]);

            return ['checked_out', $locked, 0];
        });

        [$name, $attendance, $remaining] = $action;
        if ($name === 'checked_out') $this->calculator->calculate($attendance, true);

        return $this->result($name, $office, $distance, $accuracy, $attendance, $remaining);
    }

    private function result(string $action, Office $office, int $distance, int $accuracy, ?Attendance $attendance = null, int $remaining = 0): array
    {
        return [
            'action' => $action,
            'inside' => $distance <= (int) $office->geofence_radius_meters,
            'distance_meters' => $distance,
            'accuracy_meters' => $accuracy,
            'exit_confirmation_seconds_remaining' => $remaining,
            'office' => $this->officePayload($office),
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'date' => $attendance->date->format('Y-m-d'),
                'checked_in_at' => $attendance->checked_in_at?->utc()->toISOString(),
                'checked_out_at' => $attendance->checked_out_at?->utc()->toISOString(),
            ] : null,
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
